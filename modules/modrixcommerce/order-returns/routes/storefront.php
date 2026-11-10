<?php

use App\Http\Middleware\ShareStorefrontData;
use EcoModular\OrderReturns\Http\ReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReturnController::class, 'index'])
    ->middleware(['auth', ShareStorefrontData::class])
    ->name('index');

Route::post('orders/{order}', [ReturnController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('store');
