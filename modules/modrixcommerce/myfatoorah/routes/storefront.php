<?php

/*
| Loaded under /m/modrixcommerce/myfatoorah with web middleware
| and the "modrixcommerce.myfatoorah." name prefix.
*/

use EcoModular\MyFatoorah\Http\MyFatoorahCheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('handoff/{payment}', [MyFatoorahCheckoutController::class, 'handoff'])
    ->name('handoff');

Route::match(['get', 'post'], 'response', [MyFatoorahCheckoutController::class, 'response'])
    ->middleware('throttle:60,1')
    ->name('response');
