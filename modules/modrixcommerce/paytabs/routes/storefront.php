<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use ModrixCommerce\PayTabs\Http\PayTabsReturnController;

Route::post('return/{payment}', PayTabsReturnController::class)
    ->withoutMiddleware(PreventRequestForgery::class)->middleware('throttle:60,1')->name('return');
