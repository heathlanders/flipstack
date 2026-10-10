<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Jobs\Fulfillment\ProcessTrackingWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackingWebhookController extends Controller
{
    public function handleEasyPost(Request $request)
    {
        $payload = $request->all();
        $eventType = $payload['description'] ?? '';

        // Only process tracker events
        if (! str_contains($eventType, 'tracker')) {
            return response()->json(['status' => 'ignored']);
        }

        Log::info('EasyPost webhook received', [
            'event_type' => $eventType,
            'tracking_code' => $payload['result']['tracking_code'] ?? null,
        ]);

        ProcessTrackingWebhook::dispatch($payload)->onQueue('fulfillment');

        return response()->json(['status' => 'accepted']);
    }
}
