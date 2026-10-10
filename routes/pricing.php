<?php

use App\Http\Controllers\Pricing\RepricingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:products.edit'])->prefix('pricing')->name('pricing.')->group(function () {

    // Repricing Rules
    Route::prefix('repricing')->name('repricing.')->group(function () {
        Route::get('/', [RepricingController::class, 'index'])->name('index');
        Route::get('/logs', [RepricingController::class, 'logs'])->name('logs');

        // Admin-only: create/edit/delete/run repricing rules
        Route::middleware('can:admin.access')->group(function () {
            Route::get('/create', [RepricingController::class, 'create'])->name('create');
            Route::post('/', [RepricingController::class, 'store'])->name('store');
            Route::post('/run', [RepricingController::class, 'run'])->name('run');
            Route::get('/{rule}/edit', [RepricingController::class, 'edit'])->name('edit');
            Route::put('/{rule}', [RepricingController::class, 'update'])->name('update');
            Route::delete('/{rule}', [RepricingController::class, 'destroy'])->name('destroy');
            Route::post('/{rule}/toggle', [RepricingController::class, 'toggle'])->name('toggle');
        });
    });
});
