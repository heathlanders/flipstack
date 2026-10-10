<?php

use App\Http\Controllers\Procurement\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:inventory.view'])->prefix('procurement')->name('procurement.')->group(function () {

    // Purchase Orders
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
        Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');

        // Actions
        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');

        // Receiving
        Route::get('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
        Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'processReceive'])->name('process-receive');

        // AJAX
        Route::get('/ajax/supplier-products', [PurchaseOrderController::class, 'supplierProducts'])->name('supplier-products');
    });
});
