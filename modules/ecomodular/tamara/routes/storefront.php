<?php

/*
| Loaded under /m/ecomodular/tamara with the web middleware and the
| "ecomodular.tamara." name prefix.
*/

use EcoModular\Tamara\Http\TamaraEligibilityController;
use Illuminate\Support\Facades\Route;

// Availability check at checkout. Advisory only: Tamara decides when the order is placed.
Route::post('eligibility', TamaraEligibilityController::class)
    ->middleware('throttle:20,1')
    ->name('eligibility');
