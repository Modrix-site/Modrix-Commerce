<?php

/*
| Loaded under /m/modrixcommerce/buy-together-save with web middleware
| and the "modrixcommerce.buy-together-save." name prefix.
*/

use EcoModular\BuyTogetherSave\Http\BuyTogetherStorefrontController;
use Illuminate\Support\Facades\Route;

Route::post('add', [BuyTogetherStorefrontController::class, 'add'])
    ->middleware('throttle:60,1')
    ->name('add');
