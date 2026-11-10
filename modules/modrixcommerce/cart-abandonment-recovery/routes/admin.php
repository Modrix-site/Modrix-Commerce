<?php

use EcoModular\CartAbandonmentRecovery\Http\Controllers\CartAbandonmentAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:cart_abandonment.manage')->group(function () {
    Route::get('/', [CartAbandonmentAdminController::class, 'index'])->name('index');
    Route::post('scan', [CartAbandonmentAdminController::class, 'scanNow'])->name('scan');
    Route::post('{cart}/send-email', [CartAbandonmentAdminController::class, 'sendEmail'])->name('send-email');
    Route::post('{cart}/mark-lost', [CartAbandonmentAdminController::class, 'markLost'])->name('mark-lost');
    Route::post('{cart}/mark-recovered', [CartAbandonmentAdminController::class, 'markRecovered'])->name('mark-recovered');
    Route::get('export', [CartAbandonmentAdminController::class, 'export'])->name('export');
});
