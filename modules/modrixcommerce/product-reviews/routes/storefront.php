<?php

/*
| Loaded by the platform under /m/modrixcommerce/product-reviews with the web
| middleware and the "modrixcommerce.product-reviews." name prefix.
*/

use EcoModular\ProductReviews\Http\ReviewController;
use Illuminate\Support\Facades\Route;

Route::post('products/{slug}/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth', 'throttle:5,1'])
    ->name('store');
