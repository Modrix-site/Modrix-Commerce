<?php

use EcoModular\DeliverySlots\Http\DeliverySlotsStorefrontController;
use Illuminate\Support\Facades\Route;

Route::post('/delivery-slots/select', [DeliverySlotsStorefrontController::class, 'select'])->name('select');
Route::get('/delivery-slots/slots-for-date', [DeliverySlotsStorefrontController::class, 'slotsForDate'])->name('slots-for-date');
Route::post('/delivery-slots/clear', [DeliverySlotsStorefrontController::class, 'clear'])->name('clear');
