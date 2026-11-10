<?php

/*
| Loaded under /admin/m/modrixcommerce/tap with the admin middleware and the
| "admin.modrixcommerce.tap." name prefix. Credentials are payment settings:
| they need the payments permission and a recent password confirmation.
*/

use EcoModular\Tap\Http\TapAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [TapAdminController::class, 'index'])->name('index');
    Route::put('settings', [TapAdminController::class, 'update'])->name('update');
    Route::post('test', [TapAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
