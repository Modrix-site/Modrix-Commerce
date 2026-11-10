<?php

use EcoModular\DeliverySlots\Http\DeliverySlotsAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:delivery_slots.manage')->group(function () {
    Route::get('/', [DeliverySlotsAdminController::class, 'index'])->name('index');
    Route::get('/slots', [DeliverySlotsAdminController::class, 'slots'])->name('slots');
    Route::post('/slots', [DeliverySlotsAdminController::class, 'storeSlot'])->name('slots.store');
    Route::put('/slots/{slot}', [DeliverySlotsAdminController::class, 'updateSlot'])->name('slots.update');
    Route::delete('/slots/{slot}', [DeliverySlotsAdminController::class, 'deleteSlot'])->name('slots.delete');
    Route::post('/slots/{slot}/toggle', [DeliverySlotsAdminController::class, 'toggleSlot'])->name('slots.toggle');

    Route::get('/settings', [DeliverySlotsAdminController::class, 'settings'])->name('settings');
    Route::put('/settings', [DeliverySlotsAdminController::class, 'updateSettings'])->name('settings.update');

    Route::post('/blackouts', [DeliverySlotsAdminController::class, 'storeBlackout'])->name('blackouts.store');
    Route::delete('/blackouts/{blackout}', [DeliverySlotsAdminController::class, 'deleteBlackout'])->name('blackouts.delete');
});
