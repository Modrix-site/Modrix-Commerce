<?php

/*
| Loaded under /admin/m/modrixcommerce/paytm with admin middleware
| and the "admin.modrixcommerce.paytm." name prefix.
*/

use EcoModular\Paytm\Http\PaytmAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [PaytmAdminController::class, 'index'])->name('index');
    Route::put('settings', [PaytmAdminController::class, 'update'])->name('update');
    Route::post('test', [PaytmAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
