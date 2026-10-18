<?php

/*
| Loaded by the platform under /m/ecomodular/cross-sell-upsell with the web
| middleware and the "ecomodular.cross-sell-upsell." name prefix.
*/

use EcoModular\CrossSellUpsell\Http\OfferController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::post('add', [OfferController::class, 'add'])->name('add');
    Route::post('remove', [OfferController::class, 'remove'])->name('remove');
});
