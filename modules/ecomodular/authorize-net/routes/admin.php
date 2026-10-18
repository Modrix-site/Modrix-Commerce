<?php

/*
| Loaded under /admin/m/ecomodular/authorize-net with the admin middleware and
| the "admin.ecomodular.authorize-net." name prefix. Credentials are payment
| settings: they need the payments permission and a recent password confirmation.
*/

use EcoModular\AuthorizeNet\Http\AuthorizeNetAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [AuthorizeNetAdminController::class, 'index'])->name('index');
    Route::put('settings', [AuthorizeNetAdminController::class, 'update'])->name('update');
    Route::post('test', [AuthorizeNetAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
