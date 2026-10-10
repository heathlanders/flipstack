<?php

namespace App\Http\Controllers\Amazon;

use App\Http\Controllers\Controller;
use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonApiException;
use App\Services\Communication\AmazonMessageService;
use Illuminate\Http\Request;

class AmazonMessagingController extends Controller
{
    public function actions(Request $request, Order $order)
    {
        $this->authorizeAccess($request, $order);
        $credential = $this->credentialFor($order);

        $service = new AmazonMessageService($credential);

        try {
            $messaging = $service->getMessagingActionsForOrder($order->marketplace_order_id);
            $solicitations = $service->getSolicitationActionsForOrder($order->marketplace_order_id);
        } catch (AmazonApiException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'messaging_actions' => $messaging,
            'solicitation_actions' => $solicitations,
        ]);
    }

    public function send(Request $request, Order $order)
    {
        $this->authorizeAccess($request, $order);
        $credential = $this->credentialFor($order);

        $validated = $request->validate([
            'action' => 'required|string|max:100',
            'body' => 'nullable|string|max:4000',
        ]);

        $service = new AmazonMessageService($credential);

        try {
            $message = $service->sendMessage(
                amazonOrderId: $order->marketplace_order_id,
                action: $validated['action'],
                body: $validated['body'] ?? null,
                order: $order,
            );
        } catch (AmazonApiException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['message' => $message]);
    }

    public function requestReview(Request $request, Order $order)
    {
        $this->authorizeAccess($request, $order);
        $credential = $this->credentialFor($order);

        $service = new AmazonMessageService($credential);

        try {
            $message = $service->requestReview($order->marketplace_order_id, $order);
        } catch (AmazonApiException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['message' => $message]);
    }

    protected function authorizeAccess(Request $request, Order $order): void
    {
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403);
        }

        if ($order->marketplace !== 'amazon') {
            abort(404, 'Not an Amazon order');
        }
    }

    protected function credentialFor(Order $order): IntegrationCredential
    {
        $credential = IntegrationCredential::where('id', $order->credential_id)
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            abort(404, 'Amazon credential not found for this order');
        }

        return $credential;
    }
}
