<?php

/*
| Loaded by the platform under /admin/m/ecomodular/newsletter with the admin
| middleware and the "admin.ecomodular.newsletter." name prefix.
*/

use EcoModular\Newsletter\Http\SubscriberAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:newsletter.manage')->group(function () {
    Route::get('/', [SubscriberAdminController::class, 'index'])->name('index');
    Route::get('export', [SubscriberAdminController::class, 'export'])->name('export');
    Route::delete('{subscriber}', [SubscriberAdminController::class, 'destroy'])->name('destroy');
});
