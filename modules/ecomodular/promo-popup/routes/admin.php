<?php

/*
| Loaded by the platform under /admin/m/ecomodular/promo-popup with the admin
| middleware and the "admin.ecomodular.promo-popup." name prefix.
*/

use EcoModular\PromoPopup\Http\PopupLeadAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:marketing.manage')->group(function () {
    Route::get('/', [PopupLeadAdminController::class, 'index'])->name('index');
    Route::get('export', [PopupLeadAdminController::class, 'export'])->name('export');
    Route::delete('{lead}', [PopupLeadAdminController::class, 'destroy'])->name('destroy');
});
