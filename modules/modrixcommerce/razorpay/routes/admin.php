<?php

use Illuminate\Support\Facades\Route;
use ModrixCommerce\Razorpay\Http\RazorpayAdminController;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [RazorpayAdminController::class, 'index'])->name('index');
    Route::put('settings', [RazorpayAdminController::class, 'update'])->name('update');
    Route::post('refunds/reconcile', [RazorpayAdminController::class, 'reconcileRefund'])->middleware('throttle:10,1')->name('refunds.reconcile');
});
