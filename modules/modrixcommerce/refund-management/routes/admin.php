<?php

use EcoModular\RefundManagement\Http\RefundManagementAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:refunds.manage')->group(function () {
    Route::get('/', [RefundManagementAdminController::class, 'index'])->name('index');
    Route::post('orders/{order}/refunds', [RefundManagementAdminController::class, 'storeRefund'])->name('refunds.store');
    Route::post('refunds/{refund}/credit-memo', [RefundManagementAdminController::class, 'generateCreditMemo'])->name('credit-memos.generate');
    Route::get('credit-memos/{creditMemo}/pdf', [RefundManagementAdminController::class, 'downloadCreditMemoPdf'])->name('credit-memos.pdf');
    Route::post('refunds/{refund}/payment-track', [RefundManagementAdminController::class, 'updatePaymentTrack'])->name('payment-tracks.update');
});
