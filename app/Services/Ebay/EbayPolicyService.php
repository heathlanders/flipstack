<?php

namespace App\Services\Ebay;

use App\Models\Ebay\EbayPolicy;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Services\EbayOAuthService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayPolicyService
{
    protected IntegrationCredential $credential;

    protected ?OAuthToken $token = null;

    protected string $baseUrl;

    protected string $marketplaceId;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;
        $this->baseUrl = $credential->getEbayApiUrl();
        $this->marketplaceId = $credential->marketplace_id ?? 'EBAY_US';
    }

    public function withToken(OAuthToken $token): self
    {
        $this->token = $token;

        return $this;
    }

    protected function getToken(): OAuthToken
    {
        if ($this->token) {
            return $this->token;
        }

        $this->token = $this->credential->getActiveEbayToken();

        if (! $this->token) {
            throw new Exception('No active eBay OAuth token available. Please authorize the eBay connection.');
        }

        if ($this->token->needsRefresh()) {
            $oauthService = new EbayOAuthService($this->credential);
            $this->token = $oauthService->refreshToken($this->token);
        }

        return $this->token;
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getToken()->decrypted_access_token,
            'Content-Type' => 'application/json',
            'Content-Language' => 'en-US',
            'X-EBAY-C-MARKETPLACE-ID' => $this->marketplaceId,
        ];
    }

    public function syncAllPolicies(): array
    {
        $log = EbaySyncLog::startOperation(
            $this->credential->id,
            $this->credential->team_id,
            'sync_all_policies'
        );

        try {
            $fulfillment = $this->syncPoliciesByType('fulfillment');
            $payment = $this->syncPoliciesByType('payment');
            $return = $this->syncPoliciesByType('return');

            $result = [
                'fulfillment' => $fulfillment->count(),
                'payment' => $payment->count(),
                'return' => $return->count(),
                'total' => $fulfillment->count() + $payment->count() + $return->count(),
            ];

            $log->completeSuccess($result);

            return $result;

        } catch (Exception $e) {
            $log->completeError($e->getMessage());
            throw $e;
        }
    }

    protected function syncPoliciesByType(string $type): Collection
    {
        $apiPolicies = match ($type) {
            'fulfillment' => $this->fetchFulfillmentPolicies(),
            'payment' => $this->fetchPaymentPolicies(),
            'return' => $this->fetchReturnPolicies(),
            default => [],
        };

        $syncedPolicies = collect();

        foreach ($apiPolicies as $apiPolicy) {
            $ebayPolicyId = $apiPolicy['fulfillmentPolicyId']
                ?? $apiPolicy['paymentPolicyId']
                ?? $apiPolicy['returnPolicyId']
                ?? null;

            if (! $ebayPolicyId) {
                continue;
            }

            $policy = EbayPolicy::updateOrCreate(
                [
                    'integration_credential_id' => $this->credential->id,
                    'ebay_policy_id' => $ebayPolicyId,
                ],
                [
                    'organization_id' => $this->credential->organization_id,
                    'policy_type' => $type,
                    'name' => $apiPolicy['name'] ?? 'Unnamed Policy',
                    'description' => $apiPolicy['description'] ?? null,
                    'marketplace_id' => $apiPolicy['marketplaceId'] ?? $this->marketplaceId,
                    'policy_data' => $apiPolicy,
                    'is_active' => true,
                    'last_synced_at' => now(),
                ]
            );

            $syncedPolicies->push($policy);
        }

        // Mark policies not in sync as inactive
        $ebayPolicyIds = collect($apiPolicies)->pluck('fulfillmentPolicyId')
            ->merge(collect($apiPolicies)->pluck('paymentPolicyId'))
            ->merge(collect($apiPolicies)->pluck('returnPolicyId'))
            ->filter()
            ->values();

        EbayPolicy::where('integration_credential_id', $this->credential->id)
            ->where('policy_type', $type)
            ->whereNotIn('ebay_policy_id', $ebayPolicyIds)
            ->update(['is_active' => false]);

        return $syncedPolicies;
    }

    protected function fetchFulfillmentPolicies(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/sell/account/v1/fulfillment_policy", [
                'marketplace_id' => $this->marketplaceId,
            ]);

        if ($response->successful()) {
            return $response->json('fulfillmentPolicies', []);
        }

        Log::warning('Failed to fetch fulfillment policies', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return [];
    }

    protected function fetchPaymentPolicies(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/sell/account/v1/payment_policy", [
                'marketplace_id' => $this->marketplaceId,
            ]);

        if ($response->successful()) {
            return $response->json('paymentPolicies', []);
        }

        Log::warning('Failed to fetch payment policies', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return [];
    }

    protected function fetchReturnPolicies(): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get("{$this->baseUrl}/sell/account/v1/return_policy", [
                'marketplace_id' => $this->marketplaceId,
            ]);

        if ($response->successful()) {
            return $response->json('returnPolicies', []);
        }

        Log::warning('Failed to fetch return policies', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return [];
    }

    public function createFulfillmentPolicy(array $data): EbayPolicy
    {
        $payload = $this->buildFulfillmentPolicyPayload($data);

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/sell/account/v1/fulfillment_policy", $payload);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'Failed to create fulfillment policy');
            throw new Exception($error);
        }

        $apiPolicy = $response->json();

        return EbayPolicy::create([
            'organization_id' => $this->credential->organization_id,
            'integration_credential_id' => $this->credential->id,
            'ebay_policy_id' => $apiPolicy['fulfillmentPolicyId'],
            'policy_type' => EbayPolicy::TYPE_FULFILLMENT,
            'name' => $apiPolicy['name'],
            'description' => $apiPolicy['description'] ?? null,
            'marketplace_id' => $apiPolicy['marketplaceId'] ?? $this->marketplaceId,
            'policy_data' => $apiPolicy,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);
    }

    public function updateFulfillmentPolicy(EbayPolicy $policy, array $data): EbayPolicy
    {
        $payload = $this->buildFulfillmentPolicyPayload($data);

        $response = Http::withHeaders($this->getHeaders())
            ->put("{$this->baseUrl}/sell/account/v1/fulfillment_policy/{$policy->ebay_policy_id}", $payload);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'Failed to update fulfillment policy');
            throw new Exception($error);
        }

        $apiPolicy = $response->json();

        $policy->update([
            'name' => $apiPolicy['name'],
            'description' => $apiPolicy['description'] ?? null,
            'policy_data' => $apiPolicy,
            'last_synced_at' => now(),
        ]);

        return $policy->fresh();
    }

    public function deleteFulfillmentPolicy(EbayPolicy $policy): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$this->baseUrl}/sell/account/v1/fulfillment_policy/{$policy->ebay_policy_id}");

        if ($response->successful() || $response->status() === 204) {
            $policy->delete();

            return true;
        }

        $error = $response->json('errors.0.message', 'Failed to delete fulfillment policy');
        throw new Exception($error);
    }

    protected function buildFulfillmentPolicyPayload(array $data): array
    {
        $payload = [
            'marketplaceId' => $this->marketplaceId,
            'name' => $data['name'],
            'categoryTypes' => $data['category_types'] ?? [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']],
            'handlingTime' => [
                'unit' => 'BUSINESS_DAY',
                'value' => $data['handling_time'] ?? 1,
            ],
        ];

        if (! empty($data['description'])) {
            $payload['description'] = $data['description'];
        }

        // Build shipping options
        $shippingOptions = [];

        if (isset($data['domestic_shipping'])) {
            $domestic = $data['domestic_shipping'];
            $shippingOption = [
                'optionType' => 'DOMESTIC',
                'costType' => $domestic['cost_type'] ?? 'FLAT_RATE',
            ];

            if (($domestic['cost_type'] ?? 'FLAT_RATE') === 'FLAT_RATE') {
                $shippingOption['shippingServices'] = [[
                    'shippingCarrierCode' => $domestic['carrier'] ?? 'USPS',
                    'shippingServiceCode' => $domestic['service'] ?? 'USPSPriority',
                    'shippingCost' => [
                        'currency' => 'USD',
                        'value' => (string) ($domestic['cost'] ?? '0.00'),
                    ],
                    'additionalShippingCost' => [
                        'currency' => 'USD',
                        'value' => (string) ($domestic['additional_cost'] ?? '0.00'),
                    ],
                    'freeShipping' => $domestic['free_shipping'] ?? false,
                ]];
            }

            $shippingOptions[] = $shippingOption;
        }

        if (! empty($shippingOptions)) {
            $payload['shippingOptions'] = $shippingOptions;
        }

        // Ship to locations
        $payload['shipToLocations'] = [
            'regionIncluded' => [['regionName' => 'WORLDWIDE']],
        ];

        return $payload;
    }

    public function createPaymentPolicy(array $data): EbayPolicy
    {
        $payload = [
            'marketplaceId' => $this->marketplaceId,
            'name' => $data['name'],
            'categoryTypes' => $data['category_types'] ?? [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']],
            'paymentMethods' => $data['payment_methods'] ?? [['paymentMethodType' => 'PERSONAL_CHECK']],
            'immediatePay' => $data['immediate_pay'] ?? true,
        ];

        if (! empty($data['description'])) {
            $payload['description'] = $data['description'];
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/sell/account/v1/payment_policy", $payload);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'Failed to create payment policy');
            throw new Exception($error);
        }

        $apiPolicy = $response->json();

        return EbayPolicy::create([
            'organization_id' => $this->credential->organization_id,
            'integration_credential_id' => $this->credential->id,
            'ebay_policy_id' => $apiPolicy['paymentPolicyId'],
            'policy_type' => EbayPolicy::TYPE_PAYMENT,
            'name' => $apiPolicy['name'],
            'description' => $apiPolicy['description'] ?? null,
            'marketplace_id' => $apiPolicy['marketplaceId'] ?? $this->marketplaceId,
            'policy_data' => $apiPolicy,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);
    }

    public function updatePaymentPolicy(EbayPolicy $policy, array $data): EbayPolicy
    {
        $payload = [
            'marketplaceId' => $this->marketplaceId,
            'name' => $data['name'],
            'categoryTypes' => $data['category_types'] ?? [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']],
            'paymentMethods' => $data['payment_methods'] ?? [['paymentMethodType' => 'PERSONAL_CHECK']],
            'immediatePay' => $data['immediate_pay'] ?? true,
        ];

        if (! empty($data['description'])) {
            $payload['description'] = $data['description'];
        }

        $response = Http::withHeaders($this->getHeaders())
            ->put("{$this->baseUrl}/sell/account/v1/payment_policy/{$policy->ebay_policy_id}", $payload);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'Failed to update payment policy');
            throw new Exception($error);
        }

        $apiPolicy = $response->json();

        $policy->update([
            'name' => $apiPolicy['name'],
            'description' => $apiPolicy['description'] ?? null,
            'policy_data' => $apiPolicy,
            'last_synced_at' => now(),
        ]);

        return $policy->fresh();
    }

    public function deletePaymentPolicy(EbayPolicy $policy): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$this->baseUrl}/sell/account/v1/payment_policy/{$policy->ebay_policy_id}");

        if ($response->successful() || $response->status() === 204) {
            $policy->delete();

            return true;
        }

        $error = $response->json('errors.0.message', 'Failed to delete payment policy');
        throw new Exception($error);
    }

    public function createReturnPolicy(array $data): EbayPolicy
    {
        $payload = [
            'marketplaceId' => $this->marketplaceId,
            'name' => $data['name'],
            'categoryTypes' => $data['category_types'] ?? [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']],
            'returnsAccepted' => $data['returns_accepted'] ?? true,
        ];

        if (! empty($data['description'])) {
            $payload['description'] = $data['description'];
        }

        if ($data['returns_accepted'] ?? true) {
            $payload['returnPeriod'] = [
                'unit' => 'DAY',
                'value' => $data['return_period_days'] ?? 30,
            ];
            $payload['refundMethod'] = $data['refund_method'] ?? 'MONEY_BACK';
            $payload['returnShippingCostPayer'] = $data['return_shipping_cost_payer'] ?? 'BUYER';
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post("{$this->baseUrl}/sell/account/v1/return_policy", $payload);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'Failed to create return policy');
            throw new Exception($error);
        }

        $apiPolicy = $response->json();

        return EbayPolicy::create([
            'organization_id' => $this->credential->organization_id,
            'integration_credential_id' => $this->credential->id,
            'ebay_policy_id' => $apiPolicy['returnPolicyId'],
            'policy_type' => EbayPolicy::TYPE_RETURN,
            'name' => $apiPolicy['name'],
            'description' => $apiPolicy['description'] ?? null,
            'marketplace_id' => $apiPolicy['marketplaceId'] ?? $this->marketplaceId,
            'policy_data' => $apiPolicy,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);
    }

    public function updateReturnPolicy(EbayPolicy $policy, array $data): EbayPolicy
    {
        $payload = [
            'marketplaceId' => $this->marketplaceId,
            'name' => $data['name'],
            'categoryTypes' => $data['category_types'] ?? [['name' => 'ALL_EXCLUDING_MOTORS_VEHICLES']],
            'returnsAccepted' => $data['returns_accepted'] ?? true,
        ];

        if (! empty($data['description'])) {
            $payload['description'] = $data['description'];
        }

        if ($data['returns_accepted'] ?? true) {
            $payload['returnPeriod'] = [
                'unit' => 'DAY',
                'value' => $data['return_period_days'] ?? 30,
            ];
            $payload['refundMethod'] = $data['refund_method'] ?? 'MONEY_BACK';
            $payload['returnShippingCostPayer'] = $data['return_shipping_cost_payer'] ?? 'BUYER';
        }

        $response = Http::withHeaders($this->getHeaders())
            ->put("{$this->baseUrl}/sell/account/v1/return_policy/{$policy->ebay_policy_id}", $payload);

        if (! $response->successful()) {
            $error = $response->json('errors.0.message', 'Failed to update return policy');
            throw new Exception($error);
        }

        $apiPolicy = $response->json();

        $policy->update([
            'name' => $apiPolicy['name'],
            'description' => $apiPolicy['description'] ?? null,
            'policy_data' => $apiPolicy,
            'last_synced_at' => now(),
        ]);

        return $policy->fresh();
    }

    public function deleteReturnPolicy(EbayPolicy $policy): bool
    {
        $response = Http::withHeaders($this->getHeaders())
            ->delete("{$this->baseUrl}/sell/account/v1/return_policy/{$policy->ebay_policy_id}");

        if ($response->successful() || $response->status() === 204) {
            $policy->delete();

            return true;
        }

        $error = $response->json('errors.0.message', 'Failed to delete return policy');
        throw new Exception($error);
    }

    public function getDefaultPolicies(): array
    {
        return [
            'fulfillment' => $this->credential->getDefaultEbayPolicy(EbayPolicy::TYPE_FULFILLMENT)?->ebay_policy_id
                ?? $this->credential->fulfillment_policy_id,
            'payment' => $this->credential->getDefaultEbayPolicy(EbayPolicy::TYPE_PAYMENT)?->ebay_policy_id
                ?? $this->credential->payment_policy_id,
            'return' => $this->credential->getDefaultEbayPolicy(EbayPolicy::TYPE_RETURN)?->ebay_policy_id
                ?? $this->credential->return_policy_id,
        ];
    }
}
