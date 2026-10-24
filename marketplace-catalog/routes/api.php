<?php

/*
| Store license API, under /api/marketplace/v1 (see the spec's contract table).
*/

use Illuminate\Support\Facades\Route;
use Marketplace\Http\Controllers\Api\CatalogController;
use Marketplace\Http\Controllers\Api\DownloadController;
use Marketplace\Http\Controllers\Api\LicenseController;
use Marketplace\Http\Controllers\Api\UpdateController;

Route::prefix('marketplace/v1')->middleware('throttle:60,1')->name('api.')->group(function () {
    Route::get('catalog', CatalogController::class)->name('catalog');
    Route::post('licenses/activate', [LicenseController::class, 'activate'])->name('licenses.activate');
    Route::post('licenses/check', [LicenseController::class, 'check'])->name('licenses.check');
    Route::post('licenses/deactivate', [LicenseController::class, 'deactivate'])->name('licenses.deactivate');
    Route::post('updates', UpdateController::class)->name('updates');
    Route::post('downloads', [DownloadController::class, 'request'])->name('downloads');
    Route::get('download/{token}', [DownloadController::class, 'download'])->where('token', '[A-Za-z0-9-]+')->name('download');
});
