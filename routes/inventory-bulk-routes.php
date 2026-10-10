<?php

use App\Http\Controllers\Inventory\BulkOperationsController;
use App\Http\Controllers\Inventory\ImportExportController;
use Illuminate\Support\Facades\Route;

// Add to routes/web.php: require __DIR__.'/phase4-bulk-routes.php';

Route::middleware(['auth:sanctum'])->group(function () {
    // Bulk Operations
    Route::prefix('inventory/bulk')->name('inventory.bulk.')->group(function () {
        Route::get('/', [BulkOperationsController::class, 'index'])->name('index');
        Route::post('/preview', [BulkOperationsController::class, 'preview'])->name('preview');
        Route::post('/execute', [BulkOperationsController::class, 'execute'])->name('execute');
        Route::get('/jobs', [BulkOperationsController::class, 'jobs'])->name('jobs');
        Route::get('/jobs/{job}', [BulkOperationsController::class, 'showJob'])->name('jobs.show');
        Route::post('/jobs/{job}/cancel', [BulkOperationsController::class, 'cancelJob'])->name('jobs.cancel');
        Route::post('/jobs/{job}/retry', [BulkOperationsController::class, 'retryJob'])->name('jobs.retry');
    });

    // Import
    Route::prefix('inventory/import')->name('inventory.import.')->group(function () {
        Route::get('/', [ImportExportController::class, 'importIndex'])->name('index');
        Route::get('/template', [ImportExportController::class, 'downloadTemplate'])->name('template');
        Route::post('/upload', [ImportExportController::class, 'uploadFile'])->name('upload');
        Route::post('/validate', [ImportExportController::class, 'validateImport'])->name('validate');
        Route::post('/process', [ImportExportController::class, 'processImport'])->name('process');
    });

    // Export
    Route::prefix('inventory/export')->name('inventory.export.')->group(function () {
        Route::get('/', [ImportExportController::class, 'exportIndex'])->name('index');
        Route::post('/generate', [ImportExportController::class, 'generateExport'])->name('generate');
        Route::get('/download/{job}', [ImportExportController::class, 'downloadExport'])->name('download');
    });

    // API routes for progress
    Route::prefix('api/v1/bulk')->name('api.bulk.')->group(function () {
        Route::get('/jobs/{job}/progress', [BulkOperationsController::class, 'getJobProgress'])->name('progress');
        Route::get('/jobs/{job}/errors', [BulkOperationsController::class, 'getJobErrors'])->name('errors');
    });
});
