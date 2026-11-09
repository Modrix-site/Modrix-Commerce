<?php

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::middleware('not-installed')->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::post('/', [InstallController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('store');
    // Manual installs (shared hosting) only: connect the database from the browser.
    Route::post('database/test', [InstallController::class, 'testDatabase'])
        ->middleware('throttle:10,1')
        ->name('database.test');
    Route::post('database', [InstallController::class, 'database'])
        ->middleware('throttle:5,1')
        ->name('database');
});
