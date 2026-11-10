<?php

/*
| Loaded under /admin/m/modrixcommerce/telr with the admin middleware and the
| "admin.modrixcommerce.telr." name prefix. Credentials are payment settings:
| they need the payments permission and a recent password confirmation.
*/

use EcoModular\Telr\Http\TelrAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [TelrAdminController::class, 'index'])->name('index');
    Route::put('settings', [TelrAdminController::class, 'update'])->name('update');
    Route::post('test', [TelrAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
