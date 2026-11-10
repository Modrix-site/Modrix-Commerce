<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/product-label with the admin
| middleware and the "admin.modrixcommerce.product-label." name prefix.
*/

use EcoModular\ProductLabel\Http\LabelAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:labels.manage')->group(function () {
    Route::get('/', [LabelAdminController::class, 'index'])->name('index');
});
