<?php

use App\Http\Controllers\Customer\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:inventory.view'])->prefix('customers')->name('customers.')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
    Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
    Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
    Route::post('/merge', [CustomerController::class, 'merge'])->name('merge');
});
