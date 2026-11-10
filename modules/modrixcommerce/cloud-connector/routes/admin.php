<?php

/*
| Loaded under /admin/m/modrixcommerce/cloud-connector with the admin
| middleware and the "admin.modrixcommerce.cloud-connector." name prefix.
*/

use EcoModular\CloudConnector\Http\CloudController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:extensions.view')->group(function () {
    Route::get('/', [CloudController::class, 'index'])->name('index');
});

// Connecting grants installs, so it needs install rights and a recent password confirmation.
Route::middleware(['can:extensions.install', 'password.confirm'])->group(function () {
    Route::get('connect', [CloudController::class, 'connect'])->middleware('throttle:10,1')->name('connect');
    Route::get('callback', [CloudController::class, 'callback'])->middleware('throttle:10,1')->name('callback');
    Route::post('disconnect', [CloudController::class, 'disconnect'])->middleware('throttle:10,1')->name('disconnect');
});

Route::middleware(['can:licenses.manage', 'throttle:20,1'])->group(function () {
    Route::post('licenses', [CloudController::class, 'activate'])->name('licenses.activate');
    Route::post('licenses/sync', [CloudController::class, 'sync'])->name('licenses.sync');
    Route::post('licenses/{license}/check', [CloudController::class, 'check'])->name('licenses.check');
    Route::delete('licenses/{license}', [CloudController::class, 'deactivate'])->name('licenses.deactivate');
});

Route::middleware(['can:extensions.install', 'password.confirm', 'throttle:10,1'])->group(function () {
    Route::post('install', [CloudController::class, 'installFromCloud'])->name('install-from-cloud');
    Route::post('updates/check', [CloudController::class, 'checkUpdates'])->name('updates.check');
    Route::post('licenses/{license}/install', [CloudController::class, 'install'])->name('install');
    Route::post('history/{entry}/rollback', [CloudController::class, 'rollback'])->name('rollback');
});
