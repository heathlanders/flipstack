<?php

use App\Http\Controllers\Catalog\BundleController;
use App\Http\Controllers\Inventory\BatchController;
use App\Http\Controllers\Inventory\LabelingWorkOrderController;
use App\Http\Controllers\Inventory\ListingTemplateController;
use App\Http\Controllers\Inventory\PartOutDashboardController;
use App\Http\Controllers\Inventory\PartOutTemplateController;
use App\Http\Controllers\Inventory\PartOutWorkOrderController;
use App\Http\Controllers\Inventory\PricingController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ZoneController;
use App\Http\Controllers\Product\ProductImageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory Web Routes
|--------------------------------------------------------------------------
|
| Add these routes to your routes/web.php file within the authenticated
| middleware group, or include this file from routes/web.php
|
*/

Route::middleware(['auth:sanctum', 'can:inventory.view'])->prefix('inventory')->name('inventory.')->group(function () {

    // Products routes
    Route::prefix('products')->name('products.')->group(function () {

        // Main listing with filtering
        Route::get('/', [ProductController::class, 'index'])
            ->name('index');

        // Search suggestions endpoint (for Vue component AJAX)
        Route::get('/search-suggestions', [ProductController::class, 'searchSuggestions'])
            ->name('search-suggestions');

        // Quick stats endpoint
        Route::get('/quick-stats', [ProductController::class, 'getQuickStats'])
            ->name('quick-stats');

        // Create product
        Route::get('/create', [ProductController::class, 'create'])
            ->name('create');

        Route::post('/', [ProductController::class, 'store'])
            ->name('store');

        // Export products
        Route::get('/export', [ProductController::class, 'export'])
            ->name('export');

        // Bulk actions
        Route::post('/bulk-action', [ProductController::class, 'bulkAction'])
            ->name('bulk-action');

        // Filter presets (saved searches)
        Route::prefix('filter-presets')->name('filter-presets.')->group(function () {
            Route::get('/', [ProductController::class, 'getFilterPresets'])
                ->name('index');

            Route::post('/', [ProductController::class, 'storeFilterPreset'])
                ->name('store');

            Route::delete('/{preset}', [ProductController::class, 'deleteFilterPreset'])
                ->name('destroy');

            Route::put('/{preset}/default', [ProductController::class, 'setDefaultPreset'])
                ->name('set-default');
        });

        // Single product routes (must be after other routes to avoid conflicts)
        Route::get('/{product}', [ProductController::class, 'show'])
            ->name('show');

        Route::get('/{product}/edit', [ProductController::class, 'edit'])
            ->name('edit');

        Route::put('/{product}', [ProductController::class, 'update'])
            ->name('update');

        Route::delete('/{product}', [ProductController::class, 'destroy'])
            ->name('destroy');

        Route::post('/{product}/duplicate', [ProductController::class, 'duplicate'])
            ->name('duplicate');

        Route::post('/{product}/adjust-stock', [ProductController::class, 'adjustStock'])
            ->name('adjust-stock');

        // Deletion management routes
        Route::get('/{product}/deletion-impact', [ProductController::class, 'deletionImpact'])
            ->name('deletion-impact');

        Route::delete('/{product}/force', [ProductController::class, 'forceDestroy'])
            ->name('force-destroy');

        Route::post('/{product}/restore', [ProductController::class, 'restore'])
            ->name('restore');

        // Per-image background-removal editing (reprocess / revert / replace)
        Route::post('/{product}/images/reprocess-bulk', [ProductImageController::class, 'reprocessBulk'])
            ->name('images.reprocess-bulk');
        Route::post('/{product}/images/{image}/reprocess', [ProductImageController::class, 'reprocess'])
            ->name('images.reprocess');
        Route::post('/{product}/images/{image}/revert', [ProductImageController::class, 'revert'])
            ->name('images.revert');
        Route::post('/{product}/images/{image}/replace', [ProductImageController::class, 'replace'])
            ->name('images.replace');
        Route::post('/{product}/images/sync-marketplaces', [ProductImageController::class, 'syncMarketplaces'])
            ->name('images.sync-marketplaces');
    });

    // Listing Templates
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [ListingTemplateController::class, 'index'])->name('index');
        Route::get('/{template}', [ListingTemplateController::class, 'show'])->name('show');

        // Admin-only: create/edit/delete templates
        Route::middleware('can:admin.access')->group(function () {
            Route::get('/create', [ListingTemplateController::class, 'create'])->name('create');
            Route::post('/', [ListingTemplateController::class, 'store'])->name('store');
            Route::get('/{template}/edit', [ListingTemplateController::class, 'edit'])->name('edit');
            Route::put('/{template}', [ListingTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [ListingTemplateController::class, 'destroy'])->name('destroy');
        });
    });

    // Pricing Management
    Route::prefix('pricing')->name('pricing.')->group(function () {
        Route::get('/', [PricingController::class, 'index'])->name('index');
        Route::post('/preview', [PricingController::class, 'preview'])->name('preview');
        Route::post('/apply', [PricingController::class, 'apply'])->name('apply');
    });

    // Batch/Lot Tracking
    Route::prefix('batches')->name('batches.')->group(function () {
        Route::get('/', [BatchController::class, 'index'])->name('index');
        Route::get('/create', [BatchController::class, 'create'])->name('create');
        Route::post('/', [BatchController::class, 'store'])->name('store');
        Route::get('/product-search', [BatchController::class, 'productSearch'])->name('product-search');
        Route::get('/{batch}', [BatchController::class, 'show'])->name('show');
        Route::post('/{batch}/items', [BatchController::class, 'addItem'])->name('add-item');
        Route::post('/{batch}/close', [BatchController::class, 'close'])->name('close');
    });

    // Warehouse Zones & Bins (admin-only)
    Route::prefix('warehouse')->name('warehouse.')->middleware('can:admin.access')->group(function () {
        Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');
        Route::post('/zones', [ZoneController::class, 'store'])->name('zones.store');
        Route::get('/zones/{zone}', [ZoneController::class, 'show'])->name('zones.show');
        Route::put('/zones/{zone}', [ZoneController::class, 'update'])->name('zones.update');
        Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');
        Route::post('/zones/{zone}/generate-bins', [ZoneController::class, 'generateBins'])->name('zones.generate-bins');

        Route::get('/bins', [ZoneController::class, 'bins'])->name('bins.index');
        Route::post('/bins', [ZoneController::class, 'storeBin'])->name('bins.store');
        Route::get('/bins/{bin}', [ZoneController::class, 'showBin'])->name('bins.show');
        Route::put('/bins/{bin}', [ZoneController::class, 'updateBin'])->name('bins.update');
        Route::delete('/bins/{bin}', [ZoneController::class, 'destroyBin'])->name('bins.destroy');
    });

    // Part-Out Hub
    Route::get('/part-out', [PartOutDashboardController::class, 'index'])->name('part-out.index');

    // Part-Out Templates
    Route::prefix('part-out/templates')->name('part-out.templates.')->group(function () {
        Route::get('/', [PartOutTemplateController::class, 'index'])->name('index');
        Route::get('/create', [PartOutTemplateController::class, 'create'])->name('create');
        Route::post('/', [PartOutTemplateController::class, 'store'])->name('store');
        Route::post('/from-product/{product}', [PartOutTemplateController::class, 'createFromProduct'])->name('from-product');
        Route::get('/{template}', [PartOutTemplateController::class, 'show'])->name('show');
        Route::get('/{template}/edit', [PartOutTemplateController::class, 'edit'])->name('edit');
        Route::put('/{template}', [PartOutTemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [PartOutTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{template}/duplicate', [PartOutTemplateController::class, 'duplicate'])->name('duplicate');
    });

    // Part-Out Work Orders
    Route::prefix('part-out/work-orders')->name('part-out.work-orders.')->group(function () {
        Route::get('/', [PartOutWorkOrderController::class, 'index'])->name('index');
        Route::get('/create', [PartOutWorkOrderController::class, 'create'])->name('create');
        Route::post('/', [PartOutWorkOrderController::class, 'store'])->name('store');
        Route::post('/preview-cost', [PartOutWorkOrderController::class, 'previewCostAllocation'])->name('preview-cost');
        Route::post('/refresh-pricing', [PartOutWorkOrderController::class, 'refreshPricing'])->name('refresh-pricing');
        Route::get('/{workOrder}', [PartOutWorkOrderController::class, 'show'])->name('show');
        Route::post('/{workOrder}/execute', [PartOutWorkOrderController::class, 'execute'])->name('execute');
        Route::post('/{workOrder}/cancel', [PartOutWorkOrderController::class, 'cancel'])->name('cancel');
    });

    // Labeling Work Orders
    Route::prefix('labeling')->name('labeling.')->group(function () {
        Route::get('/', [LabelingWorkOrderController::class, 'index'])->name('index');
        Route::get('/create', [LabelingWorkOrderController::class, 'create'])->name('create');
        Route::post('/', [LabelingWorkOrderController::class, 'store'])->name('store');
        Route::get('/{workOrder}', [LabelingWorkOrderController::class, 'show'])->name('show');
        Route::post('/{workOrder}/start', [LabelingWorkOrderController::class, 'start'])->name('start');
        Route::post('/{workOrder}/print-all', [LabelingWorkOrderController::class, 'printAll'])->name('print-all');
        Route::post('/{workOrder}/complete', [LabelingWorkOrderController::class, 'complete'])->name('complete');
        Route::post('/items/{item}/mark-labeled', [LabelingWorkOrderController::class, 'markLabeled'])->name('items.mark-labeled');
        Route::post('/items/{item}/assign-bin', [LabelingWorkOrderController::class, 'assignBin'])->name('items.assign-bin');
        Route::post('/items/{item}/skip', [LabelingWorkOrderController::class, 'skipItem'])->name('items.skip');
    });

    // Product Bundles
    Route::prefix('bundles')->name('bundles.')->group(function () {
        Route::get('/', [BundleController::class, 'index'])->name('index');
        Route::get('/create', [BundleController::class, 'create'])->name('create');
        Route::post('/', [BundleController::class, 'store'])->name('store');
        Route::get('/{bundle}', [BundleController::class, 'show'])->name('show');
        Route::put('/{bundle}', [BundleController::class, 'update'])->name('update');
        Route::post('/{bundle}/components', [BundleController::class, 'addComponent'])->name('add-component');
        Route::delete('/{bundle}/components/{item}', [BundleController::class, 'removeComponent'])->name('remove-component');
        Route::delete('/{bundle}', [BundleController::class, 'destroy'])->name('destroy');
    });

});
