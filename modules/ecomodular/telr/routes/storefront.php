<?php

/*
| Loaded under /m/ecomodular/telr with the web middleware and the
| "ecomodular.telr." name prefix.
*/

use EcoModular\Telr\Http\TelrFrameController;
use Illuminate\Support\Facades\Route;

Route::get('pay/{payment}', TelrFrameController::class)
    ->middleware('throttle:30,1')
    ->name('frame');
