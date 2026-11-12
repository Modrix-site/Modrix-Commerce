<?php

use Illuminate\Support\Facades\Route;
use ModrixCommerce\Sms\Http\SmsAdminController;

// Credentials live here, so the page asks for a recent password like the payment gateways.
Route::middleware(['can:sms.manage', 'password.confirm'])->group(function () {
    Route::get('/', [SmsAdminController::class, 'index'])->name('index');
    Route::put('settings', [SmsAdminController::class, 'update'])->name('update');
    Route::post('test', [SmsAdminController::class, 'test'])->middleware('throttle:5,1')->name('test');
});
