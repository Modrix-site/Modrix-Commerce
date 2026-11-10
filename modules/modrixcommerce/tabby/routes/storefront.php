<?php

/*
| Loaded under /m/modrixcommerce/tabby with the web middleware and the
| "modrixcommerce.tabby." name prefix.
*/

use EcoModular\Tabby\Http\TabbyEligibilityController;
use Illuminate\Support\Facades\Route;

// Background pre-scoring at checkout. Advisory only: the session is checked again when the order is placed.
Route::post('eligibility', TabbyEligibilityController::class)
    ->middleware('throttle:20,1')
    ->name('eligibility');
