<?php

/*
| Loaded under /admin/m/modrixcommerce/amazon-payment-services with admin middleware
| and the "admin.modrixcommerce.amazon-payment-services." name prefix.
*/

use EcoModular\AmazonPaymentServices\Http\AmazonPaymentServicesAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [AmazonPaymentServicesAdminController::class, 'index'])->name('index');
    Route::put('settings', [AmazonPaymentServicesAdminController::class, 'update'])->name('update');
    Route::post('test', [AmazonPaymentServicesAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
