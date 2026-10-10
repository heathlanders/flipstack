<?php

namespace App\Services\Marketplace;

use App\Models\Fulfillment\Order;
use App\Models\Marketplace\MarketplaceFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeeTrackingService
{
    public function recordFeesFromOrder(Order $order): void
    {
        $marketplace = $order->marketplace;
        if (! $marketplace) {
            return;
        }

        $data = $order->marketplace_data ?? [];

        match ($marketplace) {
            'ebay' => $this->extractEbayFees($order, $data),
            'shopify' => $this->extractShopifyFees($order, $data),
            'amazon' => $this->extractAmazonFees($order, $data),
            default => null,
        };
    }

    protected function extractEbayFees(Order $order, array $data): void
    {
        // eBay includes fees in order API response under totalFeeBasisAmount, totalMarketplaceFee
        $fees = [];

        // Final value fee
        $fvfAmount = $data['totalMarketplaceFee']['value']
            ?? $data['total_marketplace_fee']
            ?? null;
        if ($fvfAmount && (float) $fvfAmount > 0) {
            $fees[] = [
                'fee_type' => MarketplaceFee::TYPE_FINAL_VALUE,
                'amount' => (float) $fvfAmount,
                'description' => 'eBay final value fee',
            ];
        }

        // Payment processing fee (managed payments)
        $ppfAmount = $data['paymentProcessingFee']['value']
            ?? $data['payment_processing_fee']
            ?? null;
        if ($ppfAmount && (float) $ppfAmount > 0) {
            $fees[] = [
                'fee_type' => MarketplaceFee::TYPE_PAYMENT_PROCESSING,
                'amount' => (float) $ppfAmount,
                'description' => 'eBay payment processing fee',
            ];
        }

        foreach ($fees as $fee) {
            $this->recordFee($order, 'ebay', $fee);
        }
    }

    protected function extractShopifyFees(Order $order, array $data): void
    {
        // Shopify transaction fees from transactions array
        $transactions = $data['transactions'] ?? [];

        foreach ($transactions as $txn) {
            $fees = $txn['fees'] ?? [];
            foreach ($fees as $fee) {
                $amount = (float) ($fee['amount'] ?? 0);
                if ($amount > 0) {
                    $this->recordFee($order, 'shopify', [
                        'fee_type' => MarketplaceFee::TYPE_PAYMENT_PROCESSING,
                        'amount' => $amount,
                        'description' => $fee['type'] ?? 'Shopify transaction fee',
                        'marketplace_fee_id' => $txn['id'] ?? null,
                    ]);
                }
            }
        }
    }

    protected function extractAmazonFees(Order $order, array $data): void
    {
        // Amazon fees from order financial events
        $feeList = $data['OrderFees'] ?? $data['fees'] ?? [];

        foreach ($feeList as $fee) {
            $feeType = $fee['FeeType'] ?? $fee['type'] ?? 'other';
            $amount = (float) ($fee['FeeAmount']['CurrencyAmount'] ?? $fee['amount'] ?? 0);

            if (abs($amount) > 0) {
                $mappedType = match (strtolower($feeType)) {
                    'commission', 'referralfee' => MarketplaceFee::TYPE_REFERRAL,
                    'fbaperunitfulfillmentfee', 'fbaweightbasedfee' => MarketplaceFee::TYPE_FBA,
                    'variableclosingfee' => MarketplaceFee::TYPE_CLOSING,
                    default => MarketplaceFee::TYPE_OTHER,
                };

                $this->recordFee($order, 'amazon', [
                    'fee_type' => $mappedType,
                    'amount' => abs($amount),
                    'description' => $feeType,
                ]);
            }
        }
    }

    protected function recordFee(Order $order, string $marketplace, array $feeData): void
    {
        MarketplaceFee::updateOrCreate(
            [
                'organization_id' => $order->organization_id,
                'order_id' => $order->id,
                'marketplace' => $marketplace,
                'fee_type' => $feeData['fee_type'],
            ],
            [
                'amount' => $feeData['amount'],
                'currency' => $feeData['currency'] ?? 'USD',
                'description' => $feeData['description'] ?? null,
                'marketplace_fee_id' => $feeData['marketplace_fee_id'] ?? null,
                'fee_date' => $order->created_at?->toDateString() ?? now()->toDateString(),
            ]
        );

        Log::debug('Marketplace fee recorded', [
            'order_id' => $order->id,
            'marketplace' => $marketplace,
            'fee_type' => $feeData['fee_type'],
            'amount' => $feeData['amount'],
        ]);
    }

    public function getFeeSummary(string $organizationId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = MarketplaceFee::withoutGlobalScopes()
            ->where('organization_id', $organizationId);

        if ($startDate && $endDate) {
            $query->whereBetween('fee_date', [$startDate, $endDate]);
        }

        $byMarketplace = (clone $query)
            ->select('marketplace', DB::raw('SUM(amount) as total_fees'), DB::raw('COUNT(*) as fee_count'))
            ->groupBy('marketplace')
            ->get()
            ->keyBy('marketplace')
            ->toArray();

        $byType = (clone $query)
            ->select('fee_type', DB::raw('SUM(amount) as total_fees'), DB::raw('COUNT(*) as fee_count'))
            ->groupBy('fee_type')
            ->orderByDesc('total_fees')
            ->get()
            ->toArray();

        $totalFees = (clone $query)->sum('amount');

        return [
            'total_fees' => round($totalFees, 2),
            'by_marketplace' => $byMarketplace,
            'by_type' => $byType,
        ];
    }

    public function getFeesForOrder(string $orderId): array
    {
        return MarketplaceFee::where('order_id', $orderId)
            ->orderBy('fee_type')
            ->get()
            ->toArray();
    }
}
