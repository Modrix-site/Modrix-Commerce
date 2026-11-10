<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/newsletter with the admin
| middleware and the "admin.modrixcommerce.newsletter." name prefix.
*/

use EcoModular\Newsletter\Http\SubscriberAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:newsletter.manage')->group(function () {
    Route::get('/', [SubscriberAdminController::class, 'index'])->name('index');
    Route::get('export', [SubscriberAdminController::class, 'export'])->name('export');
    Route::delete('{subscriber}', [SubscriberAdminController::class, 'destroy'])->name('destroy');
});
