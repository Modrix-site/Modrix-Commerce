<?php

/*
| Loaded under /admin/m/modrixcommerce/paypal with the admin middleware and the
| "admin.modrixcommerce.paypal." name prefix. Credentials are payment settings:
| they need the payments permission and a recent password confirmation.
*/

use EcoModular\Paypal\Http\PaypalAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [PaypalAdminController::class, 'index'])->name('index');
    Route::put('settings', [PaypalAdminController::class, 'update'])->name('update');
    Route::post('test', [PaypalAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
