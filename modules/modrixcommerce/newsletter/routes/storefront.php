<?php

/*
| Loaded by the platform under /m/modrixcommerce/newsletter with the web
| middleware and the "modrixcommerce.newsletter." name prefix.
*/

use EcoModular\Newsletter\Http\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('subscribe', [SubscriptionController::class, 'subscribe'])->middleware('throttle:5,1')->name('subscribe');
Route::get('unsubscribe/{token}', [SubscriptionController::class, 'unsubscribe'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1')
    ->name('unsubscribe');
