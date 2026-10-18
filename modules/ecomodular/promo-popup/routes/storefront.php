<?php

/*
| Loaded by the platform under /m/ecomodular/promo-popup with the web
| middleware and the "ecomodular.promo-popup." name prefix.
*/

use EcoModular\PromoPopup\Http\PopupLeadController;
use Illuminate\Support\Facades\Route;

Route::post('lead', [PopupLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('lead');
