<?php

use EcoModular\BotProtection\Http\Controllers\BotDashboardController;
use EcoModular\BotProtection\Http\Controllers\BotDetectionQueueController;
use EcoModular\BotProtection\Http\Controllers\BotIpManagementController;
use EcoModular\BotProtection\Http\Controllers\BotReportsController;
use EcoModular\BotProtection\Http\Controllers\BotRulesEngineController;
use EcoModular\BotProtection\Http\Controllers\BotSecurityCenterController;
use EcoModular\BotProtection\Http\Controllers\BotSettingsController;
use EcoModular\BotProtection\Http\Controllers\BotTrafficAnalyticsController;
use Illuminate\Support\Facades\Route;

// ── Dashboard ──────────────────────────────────────────
Route::middleware('can:bot_protection.view_dashboard')->group(function () {
    Route::get('/', [BotDashboardController::class, 'index'])->name('dashboard');
});

// ── Security Center ───────────────────────────────────
Route::middleware('can:bot_protection.manage_security')->prefix('security')->name('security.')->group(function () {
    Route::get('/', [BotSecurityCenterController::class, 'index'])->name('index');
    Route::post('/lockdown', [BotSecurityCenterController::class, 'toggleLockdown'])->name('lockdown');
    Route::post('/flush-cache', [BotSecurityCenterController::class, 'flushThreatCache'])->name('flush-cache');
});

// ── Traffic Analytics ─────────────────────────────────
Route::middleware('can:bot_protection.view_dashboard')->prefix('traffic')->name('traffic.')->group(function () {
    Route::get('/', [BotTrafficAnalyticsController::class, 'index'])->name('index');
});

// ── Detection Queue ───────────────────────────────────
Route::middleware('can:bot_protection.manage_security')->prefix('queue')->name('queue.')->group(function () {
    Route::get('/', [BotDetectionQueueController::class, 'index'])->name('index');
    Route::post('/{event}/resolve', [BotDetectionQueueController::class, 'resolve'])->name('resolve');
    Route::post('/{event}/block-ip', [BotDetectionQueueController::class, 'blockIp'])->name('block-ip');
    Route::post('/{event}/whitelist-ip', [BotDetectionQueueController::class, 'whitelistIp'])->name('whitelist-ip');
});

// ── IP Management ─────────────────────────────────────
Route::middleware('can:bot_protection.manage_ips')->prefix('ips')->name('ips.')->group(function () {
    Route::get('/', [BotIpManagementController::class, 'index'])->name('index');
    Route::post('/blocked', [BotIpManagementController::class, 'storeBlockedIp'])->name('blocked.store');
    Route::delete('/blocked/{ip}', [BotIpManagementController::class, 'destroyBlockedIp'])->name('blocked.destroy');
    Route::post('/allowed', [BotIpManagementController::class, 'storeAllowedIp'])->name('allowed.store');
    Route::delete('/allowed/{ip}', [BotIpManagementController::class, 'destroyAllowedIp'])->name('allowed.destroy');
    Route::post('/countries', [BotIpManagementController::class, 'updateCountries'])->name('countries.update');
});

// ── Rules Engine ──────────────────────────────────────
Route::middleware('can:bot_protection.manage_rules')->prefix('rules')->name('rules.')->group(function () {
    Route::get('/', [BotRulesEngineController::class, 'index'])->name('index');
    Route::post('/', [BotRulesEngineController::class, 'store'])->name('store');
    Route::post('/{rule}/toggle', [BotRulesEngineController::class, 'toggle'])->name('toggle');
    Route::delete('/{rule}', [BotRulesEngineController::class, 'destroy'])->name('destroy');
});

// ── Reports & Analytics ───────────────────────────────
Route::middleware('can:bot_protection.view_dashboard')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [BotReportsController::class, 'index'])->name('index');
});

Route::middleware('can:bot_protection.export_reports')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/export/csv', [BotReportsController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export/excel', [BotReportsController::class, 'exportExcel'])->name('export.excel');
});

// ── Settings ──────────────────────────────────────────
Route::middleware('can:bot_protection.manage_settings')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [BotSettingsController::class, 'index'])->name('index');
    Route::post('/', [BotSettingsController::class, 'update'])->name('update');
});
