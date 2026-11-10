<?php

/*
| Loaded by the platform under /m/modrixcommerce/promo-popup with the web
| middleware and the "modrixcommerce.promo-popup." name prefix.
*/

use EcoModular\PromoPopup\Http\PopupLeadController;
use Illuminate\Support\Facades\Route;

Route::post('lead', [PopupLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('lead');
