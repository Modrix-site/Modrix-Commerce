<?php

/*
| Loaded under /admin/m/modrixcommerce/cashfree with admin middleware
| and the "admin.modrixcommerce.cashfree." name prefix.
*/

use EcoModular\Cashfree\Http\CashfreeAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['can:payments.manage', 'password.confirm'])->group(function () {
    Route::get('/', [CashfreeAdminController::class, 'index'])->name('index');
    Route::put('settings', [CashfreeAdminController::class, 'update'])->name('update');
    Route::post('test', [CashfreeAdminController::class, 'test'])->middleware('throttle:10,1')->name('test');
});
