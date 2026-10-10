<?php

use App\Http\Controllers\Shopify\ShopifyMarketplaceController;
use App\Http\Controllers\Shopify\ShopifyOAuthController;
use App\Http\Controllers\Shopify\ShopifyProductMatchController;
use App\Http\Controllers\Shopify\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

// Shopify Webhooks (no auth - verified via HMAC)
Route::post('/shopify/webhooks', [ShopifyWebhookController::class, 'handle'])
    ->name('shopify.webhooks')
    ->withoutMiddleware(['web', 'auth', 'csrf']);

// Shopify OAuth
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'onboarding',
])->group(function () {

    Route::get('/shopify/auth/install', [ShopifyOAuthController::class, 'install'])->name('shopify.auth.install');
    Route::get('/shopify/auth/callback', [ShopifyOAuthController::class, 'callback'])->name('shopify.auth.callback');
    Route::post('/shopify/auth/disconnect', [ShopifyOAuthController::class, 'disconnect'])->name('shopify.auth.disconnect');

    // Shopify Marketplace
    Route::prefix('marketplaces/shopify')->name('marketplaces.shopify.')->group(function () {
        Route::get('/', [ShopifyMarketplaceController::class, 'index'])->name('index');
        Route::get('/products', [ShopifyMarketplaceController::class, 'products'])->name('products');
        Route::post('/products/{product}/sync', [ShopifyMarketplaceController::class, 'syncProduct'])->name('products.sync');
        Route::post('/sync/bulk', [ShopifyMarketplaceController::class, 'syncBulk'])->name('sync.bulk');
        Route::post('/import/start', [ShopifyMarketplaceController::class, 'startImport'])->name('import.start');
        Route::get('/logs', [ShopifyMarketplaceController::class, 'logs'])->name('logs');
        Route::get('/category-settings', [ShopifyMarketplaceController::class, 'categorySettings'])->name('category-settings');
        Route::post('/category-settings', [ShopifyMarketplaceController::class, 'updateCategorySettings'])->name('category-settings.update');
        Route::get('/collections', [ShopifyMarketplaceController::class, 'fetchCollections'])->name('collections');
        Route::post('/auto-sync', [ShopifyMarketplaceController::class, 'toggleAutoSync'])->name('auto-sync.toggle');

        // Product Matching
        Route::post('/match/start', [ShopifyProductMatchController::class, 'startMatch'])->name('match.start');
        Route::get('/match/review', [ShopifyProductMatchController::class, 'review'])->name('match.review');
        Route::get('/match/status/{bulkJob}', [ShopifyProductMatchController::class, 'status'])->name('match.status');
        Route::post('/match/confirm', [ShopifyProductMatchController::class, 'confirm'])->name('match.confirm');
        Route::post('/match/confirm-all', [ShopifyProductMatchController::class, 'confirmAll'])->name('match.confirm-all');
        Route::post('/match/{match}/reject', [ShopifyProductMatchController::class, 'reject'])->name('match.reject');
        Route::post('/match/{match}/pair', [ShopifyProductMatchController::class, 'pair'])->name('match.pair');
        Route::post('/match/{match}/ignore', [ShopifyProductMatchController::class, 'ignore'])->name('match.ignore');
        Route::get('/match/search-products', [ShopifyProductMatchController::class, 'searchProducts'])->name('match.search-products');
    });
});
