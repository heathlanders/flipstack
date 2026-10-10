<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Jobs\Fulfillment\ProcessShipStationWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShipStationWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $resourceType = $request->input('resource_type');
        $resourceUrl = $request->input('resource_url');

        if (! in_array($resourceType, ['SHIP_NOTIFY', 'ORDER_NOTIFY'])) {
            return response()->json(['status' => 'ignored']);
        }

        if (! $resourceUrl) {
            return response()->json(['status' => 'missing_resource_url'], 400);
        }

        Log::info('ShipStation webhook received', [
            'resource_type' => $resourceType,
            'resource_url' => $resourceUrl,
        ]);

        ProcessShipStationWebhook::dispatch($resourceType, $resourceUrl)->onQueue('fulfillment');

        return response()->json(['status' => 'accepted']);
    }
}
