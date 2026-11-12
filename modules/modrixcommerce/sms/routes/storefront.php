<?php

use App\Http\Middleware\ShareStorefrontData;
use Illuminate\Support\Facades\Route;
use ModrixCommerce\Sms\Http\PhoneController;

Route::middleware(['auth', ShareStorefrontData::class])->group(function () {
    Route::get('verify', [PhoneController::class, 'verify'])->name('verify');
    Route::get('account', [PhoneController::class, 'account'])->name('account');
});

Route::middleware('auth')->group(function () {
    Route::post('verify', [PhoneController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
    Route::post('resend', [PhoneController::class, 'resend'])->middleware('throttle:6,1')->name('resend');
    Route::post('change', [PhoneController::class, 'change'])->middleware('throttle:6,1')->name('change');
    Route::put('preferences', [PhoneController::class, 'preferences'])->middleware('throttle:20,1')->name('preferences');
});
