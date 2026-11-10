<?php

/*
| Loaded by the platform under /m/modrixcommerce/frequently-bought-together with
| the web middleware and the "modrixcommerce.frequently-bought-together." name
| prefix.
*/

use EcoModular\FrequentlyBoughtTogether\Http\BundleController;
use Illuminate\Support\Facades\Route;

Route::post('add', [BundleController::class, 'add'])
    ->middleware('throttle:30,1')
    ->name('add');
