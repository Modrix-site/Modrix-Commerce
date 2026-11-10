<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/product-reviews with the
| admin middleware and the "admin.modrixcommerce.product-reviews." name prefix.
| Every route must declare a permission.
*/

use EcoModular\ProductReviews\Http\ReviewAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:reviews.moderate')->group(function () {
    Route::get('/', [ReviewAdminController::class, 'index'])->name('index');
    Route::post('{review}/approve', [ReviewAdminController::class, 'approve'])->name('approve');
    Route::post('{review}/reject', [ReviewAdminController::class, 'reject'])->name('reject');
    Route::delete('{review}', [ReviewAdminController::class, 'destroy'])->name('destroy');
});
