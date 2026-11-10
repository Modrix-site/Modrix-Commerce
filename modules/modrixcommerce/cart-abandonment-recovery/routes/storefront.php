<?php

use EcoModular\CartAbandonmentRecovery\Http\Controllers\CartRecoveryStorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('recover/{token}', [CartRecoveryStorefrontController::class, 'recover'])->name('recover');
Route::post('capture-email', [CartRecoveryStorefrontController::class, 'captureEmail'])->name('capture-email');
Route::post('email-cart', [CartRecoveryStorefrontController::class, 'emailCart'])->name('email-cart');
Route::get('unsubscribe/{token}', [CartRecoveryStorefrontController::class, 'unsubscribe'])->name('unsubscribe');
Route::get('track/open/{email}', [CartRecoveryStorefrontController::class, 'trackOpen'])->name('track.open');
Route::get('track/click/{email}', [CartRecoveryStorefrontController::class, 'trackClick'])->name('track.click');
