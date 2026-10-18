<?php

/*
| Loaded under /admin/m/ecomodular/tamara with the admin middleware and the
| "admin.ecomodular.tamara." name prefix. Credentials are payment settings:
| they need the payments permission and a recent password confirmation.
*/

use EcoModular\Tamara\Http\TamaraAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [TamaraAdminController::class, 'index'])->name('index');
    Route::put('settings', [TamaraAdminController::class, 'update'])->name('update');
    Route::post('test', [TamaraAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
