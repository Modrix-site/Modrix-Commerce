<?php

use EcoModular\SocialProofNotifications\Http\Controllers\SocialProofStorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('feed', [SocialProofStorefrontController::class, 'feed'])->name('feed');
Route::post('heartbeat', [SocialProofStorefrontController::class, 'heartbeat'])->name('heartbeat');
Route::post('track/impression/{event}', [SocialProofStorefrontController::class, 'trackImpression'])->name('track.impression');
Route::post('track/click/{event}', [SocialProofStorefrontController::class, 'trackClick'])->name('track.click');
