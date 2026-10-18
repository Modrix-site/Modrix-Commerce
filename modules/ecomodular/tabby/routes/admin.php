<?php

/*
| Loaded under /admin/m/ecomodular/tabby with the admin middleware and the
| "admin.ecomodular.tabby." name prefix. Credentials are payment settings:
| they need the payments permission and a recent password confirmation.
*/

use EcoModular\Tabby\Http\TabbyAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [TabbyAdminController::class, 'index'])->name('index');
    Route::put('settings', [TabbyAdminController::class, 'update'])->name('update');
    Route::post('test', [TabbyAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
    Route::post('webhook', [TabbyAdminController::class, 'registerWebhook'])->middleware('throttle:10,1')->name('webhook');
});
