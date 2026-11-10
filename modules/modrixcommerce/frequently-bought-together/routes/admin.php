<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/frequently-bought-together
| with the admin middleware and the
| "admin.modrixcommerce.frequently-bought-together." name prefix.
*/

use EcoModular\FrequentlyBoughtTogether\Http\BundleAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:bundles.manage')->group(function () {
    Route::get('/', [BundleAdminController::class, 'index'])->name('index');
});
