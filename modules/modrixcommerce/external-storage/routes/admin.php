<?php

use EcoModular\ExternalStorage\Http\Controllers\ExternalStorageAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:external_storage.manage')->group(function () {
    Route::get('/', [ExternalStorageAdminController::class, 'index'])->name('index');
    Route::put('settings', [ExternalStorageAdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('test-connection', [ExternalStorageAdminController::class, 'testConnection'])->name('test-connection');
    Route::post('upload', [ExternalStorageAdminController::class, 'upload'])->name('upload');
    Route::post('sync-all', [ExternalStorageAdminController::class, 'syncAll'])->name('sync-all');
    Route::post('products/{product}/sync', [ExternalStorageAdminController::class, 'syncProduct'])->name('products.sync');
    Route::delete('files/{file}', [ExternalStorageAdminController::class, 'destroy'])->name('files.destroy');
});
