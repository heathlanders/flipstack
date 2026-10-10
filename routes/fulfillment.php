<?php

use App\Http\Controllers\Fulfillment\DocumentController;
use App\Http\Controllers\Fulfillment\OrderController;
use App\Http\Controllers\Fulfillment\PackController;
use App\Http\Controllers\Fulfillment\PickController;
use App\Http\Controllers\Fulfillment\ReturnController;
use App\Http\Controllers\Fulfillment\RoutingController;
use App\Http\Controllers\Fulfillment\SettingsController;
use App\Http\Controllers\Fulfillment\ShipController;
use App\Http\Controllers\Fulfillment\ShippingEasyWebhookController;
use App\Http\Controllers\Fulfillment\ShippingRuleController;
use App\Http\Controllers\Fulfillment\ShipStationWebhookController;
use App\Http\Controllers\Fulfillment\TrackingWebhookController;
use Illuminate\Support\Facades\Route;

// Fulfillment routes (authenticated)
Route::middleware(['auth:sanctum', 'can:orders.view'])->prefix('fulfillment')->name('fulfillment.')->group(function () {

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
    Route::post('/orders/{order}/hold', [OrderController::class, 'hold'])->name('orders.hold');
    Route::post('/orders/{order}/release', [OrderController::class, 'release'])->name('orders.release');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/manual-ship', [OrderController::class, 'manualShip'])->name('orders.manual-ship');
    Route::post('/shipments/{shipment}/refresh-tracking', [OrderController::class, 'refreshTracking'])->name('shipments.refresh-tracking');
    Route::post('/orders/bulk-assign', [OrderController::class, 'bulkAssign'])->name('orders.bulk-assign');
    Route::post('/orders/bulk-priority', [OrderController::class, 'bulkPriority'])->name('orders.bulk-priority');
    Route::get('/orders/{order}/audit', [OrderController::class, 'auditTrail'])->name('orders.audit');

    // Picking
    Route::get('/pick', [PickController::class, 'pickList'])->name('pick.index');
    Route::post('/pick/{order}/start', [PickController::class, 'startPick'])->name('pick.start');
    Route::post('/pick/{orderItem}/pick', [PickController::class, 'pickItem'])->name('pick.item');
    Route::post('/pick/{orderItem}/short', [PickController::class, 'shortItem'])->name('pick.short');
    Route::post('/pick/{order}/complete', [PickController::class, 'completePick'])->name('pick.complete');
    Route::post('/pick/print', [PickController::class, 'printPickList'])->name('pick.print');

    // Packing
    Route::get('/pack', [PackController::class, 'packQueue'])->name('pack.index');
    Route::post('/pack/{order}/start', [PackController::class, 'startPack'])->name('pack.start');
    Route::get('/pack/{order}/rates', [PackController::class, 'getRates'])->name('pack.rates');
    Route::post('/pack/{order}/purchase-label', [PackController::class, 'purchaseLabel'])->name('pack.purchase-label');
    Route::post('/pack/{order}/complete', [PackController::class, 'completePack'])->name('pack.complete');
    Route::post('/pack/{order}/packing-slip', [PackController::class, 'printPackingSlip'])->name('pack.packing-slip');

    // Shipping
    Route::get('/ship', [ShipController::class, 'shipQueue'])->name('ship.index');
    Route::post('/ship/{shipment}/confirm', [ShipController::class, 'markShipped'])->name('ship.confirm');
    Route::post('/ship/{shipment}/void', [ShipController::class, 'voidLabel'])->name('ship.void');
    Route::post('/ship/bulk-ship', [ShipController::class, 'bulkMarkShipped'])->name('ship.bulk');
    Route::post('/ship/batch-print', [ShipController::class, 'batchPrintLabels'])->name('ship.batch-print');
    Route::get('/ship/{shipment}/label', [ShipController::class, 'downloadLabel'])->name('ship.label');

    // Returns
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{returnRequest}', [ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{order}/create', [ReturnController::class, 'create'])->name('returns.create');
    Route::post('/returns/{returnRequest}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{returnRequest}/receive', [ReturnController::class, 'receive'])->name('returns.receive');
    Route::post('/returns/{returnRequest}/inspect', [ReturnController::class, 'inspect'])->name('returns.inspect');
    Route::post('/returns/{returnRequest}/refund', [ReturnController::class, 'refund'])->name('returns.refund');
    Route::post('/returns/{returnRequest}/deny', [ReturnController::class, 'deny'])->name('returns.deny');
    Route::post('/returns/{returnRequest}/restock', [ReturnController::class, 'restock'])->name('returns.restock');

    // Routing Rules (admin-only)
    Route::prefix('routing')->name('routing.')->middleware('can:admin.access')->group(function () {
        Route::get('/', [RoutingController::class, 'index'])->name('index');
        Route::get('/create', [RoutingController::class, 'create'])->name('create');
        Route::post('/', [RoutingController::class, 'store'])->name('store');
        Route::get('/{rule}/edit', [RoutingController::class, 'edit'])->name('edit');
        Route::put('/{rule}', [RoutingController::class, 'update'])->name('update');
        Route::delete('/{rule}', [RoutingController::class, 'destroy'])->name('destroy');
        Route::post('/{rule}/toggle', [RoutingController::class, 'toggle'])->name('toggle');
        Route::post('/test', [RoutingController::class, 'test'])->name('test');
    });

    // Document downloads
    Route::get('/documents/{type}/{filename}', [DocumentController::class, 'download'])->name('documents.download');

    // Location shipping address verification
    Route::post('/locations/{location}/verify-address', [\App\Http\Controllers\Inventory\LocationController::class, 'verifyShipFromAddress'])->name('locations.verify-address');

    // Shipping Rules (admin-only)
    Route::prefix('shipping-rules')->name('shipping-rules.')->middleware('can:admin.access')->group(function () {
        Route::get('/', [ShippingRuleController::class, 'index'])->name('index');
        Route::get('/create', [ShippingRuleController::class, 'create'])->name('create');
        Route::post('/', [ShippingRuleController::class, 'store'])->name('store');
        Route::get('/{rule}/edit', [ShippingRuleController::class, 'edit'])->name('edit');
        Route::put('/{rule}', [ShippingRuleController::class, 'update'])->name('update');
        Route::delete('/{rule}', [ShippingRuleController::class, 'destroy'])->name('destroy');
        Route::post('/{rule}/toggle', [ShippingRuleController::class, 'toggle'])->name('toggle');
        Route::post('/test', [ShippingRuleController::class, 'test'])->name('test');
    });

    // Settings (admin-only)
    Route::prefix('settings')->name('settings.')->middleware('can:admin.access')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/shipping', [SettingsController::class, 'updateShipping'])->name('shipping');
        Route::put('/labels', [SettingsController::class, 'updateLabels'])->name('labels');
        Route::put('/workflow', [SettingsController::class, 'updateWorkflow'])->name('workflow');
        Route::put('/returns', [SettingsController::class, 'updateReturns'])->name('returns');
        Route::put('/notifications', [SettingsController::class, 'updateNotifications'])->name('notifications');

        // Shipping provider
        Route::put('/provider', [SettingsController::class, 'updateShippingProvider'])->name('provider');
        Route::post('/shipstation', [SettingsController::class, 'storeShipStationCredentials'])->name('shipstation');
        Route::post('/shippingeasy', [SettingsController::class, 'storeShippingEasyCredentials'])->name('shippingeasy');

        // Carrier management
        Route::post('/carriers/sync', [SettingsController::class, 'syncCarriers'])->name('carriers.sync');
        Route::put('/carriers/{carrier}', [SettingsController::class, 'updateCarrier'])->name('carriers.update');

        // Package presets
        Route::post('/presets', [SettingsController::class, 'storePreset'])->name('presets.store');
        Route::put('/presets/{preset}', [SettingsController::class, 'updatePreset'])->name('presets.update');
        Route::delete('/presets/{preset}', [SettingsController::class, 'destroyPreset'])->name('presets.destroy');
    });
});

// Webhook (no auth - signature verified in controller, CSRF disabled)
Route::post('/webhooks/easypost', [TrackingWebhookController::class, 'handleEasyPost'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('webhooks.easypost');

Route::post('/webhooks/shipstation', [ShipStationWebhookController::class, 'handle'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('webhooks.shipstation');

Route::post('/webhooks/shippingeasy', [ShippingEasyWebhookController::class, 'handle'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('webhooks.shippingeasy');
