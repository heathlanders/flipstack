<?php

use App\Http\Controllers\Amazon\AmazonMarketplaceController;
use App\Http\Controllers\Amazon\AmazonMessagingController;
use App\Http\Controllers\Amazon\AmazonOAuthController;
use Illuminate\Support\Facades\Route;

// Amazon SNS notifications already registered in routes/api.php (no auth)

// Amazon OAuth + Marketplace UI
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'onboarding',
])->group(function () {

    Route::get('/amazon/auth/install', [AmazonOAuthController::class, 'install'])->name('amazon.auth.install');
    Route::get('/amazon/auth/callback', [AmazonOAuthController::class, 'callback'])->name('amazon.auth.callback');
    Route::post('/amazon/auth/disconnect', [AmazonOAuthController::class, 'disconnect'])->name('amazon.auth.disconnect');

    Route::prefix('marketplaces/amazon')->name('marketplaces.amazon.')->group(function () {
        Route::get('/', [AmazonMarketplaceController::class, 'index'])->name('index');
        Route::get('/products', [AmazonMarketplaceController::class, 'products'])->name('products');
        Route::post('/products/{product}/sync', [AmazonMarketplaceController::class, 'syncProduct'])->name('products.sync');
        Route::post('/import/start', [AmazonMarketplaceController::class, 'startImport'])->name('import.start');
        Route::get('/logs', [AmazonMarketplaceController::class, 'logs'])->name('logs');
        Route::post('/auto-sync', [AmazonMarketplaceController::class, 'toggleAutoSync'])->name('auto-sync.toggle');

        Route::prefix('orders/{order}/messages')->name('messages.')->group(function () {
            Route::get('/actions', [AmazonMessagingController::class, 'actions'])->name('actions');
            Route::post('/send', [AmazonMessagingController::class, 'send'])->name('send');
            Route::post('/request-review', [AmazonMessagingController::class, 'requestReview'])->name('request-review');
        });
    });
});
