<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Jobs\Fulfillment\ProcessShippingEasyWebhook;
use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\ShippingEasyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShippingEasyWebhookController extends Controller
{
    /**
     * Map SE workflow_state values to internal event types.
     */
    protected array $workflowStateMap = [
        'label_ready' => 'label.purchased',
        'label_printed' => 'label.purchased',
        'cancelled' => 'shipment.cancelled',
        'drop_shipped' => 'shipment.shipped',
        'label_pending' => 'label.pending',
    ];

    protected array $processableEvents = [
        'label.purchased',
        'shipment.cancelled',
        'shipment.shipped',
    ];

    public function handle(Request $request)
    {
        // SE sends auth as query params or headers
        $apiKey = $request->input('api_key') ?? $request->header('api_key');
        $signature = $request->input('api_signature') ?? $request->header('api_signature');
        $timestamp = $request->input('api_timestamp') ?? $request->header('api_timestamp');

        if (! $apiKey || ! $signature || ! $timestamp) {
            Log::warning('ShippingEasy webhook missing auth parameters');

            return response()->json(['error' => 'Missing authentication parameters'], 401);
        }

        // Look up the credential by API key (client_id is encrypted, so we must decrypt and compare)
        $credential = IntegrationCredential::where('platform', 'shippingeasy')
            ->where('is_active', true)
            ->get()
            ->first(fn ($cred) => $cred->decrypted_client_id === $apiKey);

        if (! $credential) {
            Log::warning('ShippingEasy webhook from unknown API key', ['api_key' => $apiKey]);

            return response()->json(['error' => 'Unknown API key'], 401);
        }

        // Build params for signature verification (all query params except api_signature)
        $verifyParams = $request->query();
        unset($verifyParams['api_signature']);

        // If params came via headers instead of query string, build them manually
        if (empty($verifyParams['api_key'])) {
            $verifyParams['api_key'] = $apiKey;
            $verifyParams['api_timestamp'] = $timestamp;
        }

        // Verify HMAC signature
        $verified = ShippingEasyService::verifyWebhookSignature(
            apiSecret: $credential->decrypted_client_secret,
            method: $request->method(),
            path: '/'.ltrim($request->path(), '/'),
            params: $verifyParams,
            body: $request->getContent(),
            providedSignature: $signature,
        );

        if (! $verified) {
            Log::warning('ShippingEasy webhook signature verification failed', [
                'api_key' => $apiKey,
                'org_id' => $credential->organization_id,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        // Determine event type from workflow_state (actual SE format) or event_type (legacy/custom)
        $eventType = $this->resolveEventType($payload);

        if (! $eventType || ! in_array($eventType, $this->processableEvents)) {
            Log::info('ShippingEasy webhook event ignored', [
                'event_type' => $eventType,
                'workflow_state' => $payload['shipment']['workflow_state'] ?? $payload['event_type'] ?? 'unknown',
            ]);

            return response()->json(['status' => 'ignored']);
        }

        Log::info('ShippingEasy webhook received', [
            'event_type' => $eventType,
            'org_id' => $credential->organization_id,
        ]);

        ProcessShippingEasyWebhook::dispatch($eventType, $payload, $credential->organization_id)
            ->onQueue('fulfillment');

        return response()->json(['status' => 'accepted']);
    }

    protected function resolveEventType(array $payload): ?string
    {
        // SE callback format: top-level "shipment" with "workflow_state"
        $workflowState = $payload['shipment']['workflow_state'] ?? null;
        if ($workflowState && isset($this->workflowStateMap[$workflowState])) {
            return $this->workflowStateMap[$workflowState];
        }

        // Fallback: explicit event_type field (for forward compatibility)
        $eventType = $payload['event_type'] ?? null;
        if ($eventType && in_array($eventType, $this->processableEvents)) {
            return $eventType;
        }

        return $eventType;
    }
}
