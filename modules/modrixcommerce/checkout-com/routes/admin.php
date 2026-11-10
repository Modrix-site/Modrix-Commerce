<?php

/*
| Loaded under /admin/m/modrixcommerce/checkout-com with the admin middleware and
| the "admin.modrixcommerce.checkout-com." name prefix. Credentials are payment
| settings: they need the payments permission and a recent password confirmation.
*/

use EcoModular\CheckoutCom\Http\CheckoutComAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [CheckoutComAdminController::class, 'index'])->name('index');
    Route::put('settings', [CheckoutComAdminController::class, 'update'])->name('update');
    Route::post('test', [CheckoutComAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
