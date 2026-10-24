<?php

/*
| Store license API, under /api/marketplace/v1 (see the spec's contract table).
*/

use Illuminate\Support\Facades\Route;

Route::prefix('marketplace/v1')->middleware('throttle:60,1')->name('api.')->group(function () {
});
