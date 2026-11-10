<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/gdpr-cookie-consent with
| the admin middleware and the "admin.modrixcommerce.gdpr-cookie-consent." name
| prefix.
*/

use EcoModular\GdprCookieConsent\Http\ConsentAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:privacy.manage')->group(function () {
    Route::get('/', [ConsentAdminController::class, 'index'])->name('index');
    Route::get('export', [ConsentAdminController::class, 'export'])->name('export');
    Route::delete('{record}', [ConsentAdminController::class, 'destroy'])->name('destroy');
});
