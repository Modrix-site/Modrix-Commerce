<?php

use EcoModular\FraudDetection\Http\Controllers\FraudDashboardController;
use EcoModular\FraudDetection\Http\Controllers\FraudQueueController;
use EcoModular\FraudDetection\Http\Controllers\FraudRulesController;
use EcoModular\FraudDetection\Http\Controllers\FraudReportsController;
use EcoModular\FraudDetection\Http\Controllers\FraudSettingsController;
use Illuminate\Support\Facades\Route;

// ── Dashboard ──────────────────────────────────────────
Route::middleware('can:fraud_detection.view_dashboard')->group(function () {
    Route::get('/', [FraudDashboardController::class, 'index'])->name('dashboard');
});

// ── Fraud Queue ────────────────────────────────────────
Route::middleware('can:fraud_detection.manage_queue')->prefix('queue')->name('queue.')->group(function () {
    Route::get('/', [FraudQueueController::class, 'index'])->name('index');
    Route::get('{fraudScore}', [FraudQueueController::class, 'show'])->name('show');
    Route::post('{fraudScore}/approve', [FraudQueueController::class, 'approve'])->name('approve');
    Route::post('{fraudScore}/reject', [FraudQueueController::class, 'reject'])->name('reject');
    Route::post('{fraudScore}/review', [FraudQueueController::class, 'markUnderReview'])->name('review');
    Route::post('{fraudScore}/note', [FraudQueueController::class, 'addNote'])->name('note');
});

// ── Rules Management ───────────────────────────────────
Route::middleware('can:fraud_detection.manage_rules')->prefix('rules')->name('rules.')->group(function () {
    Route::get('/', [FraudRulesController::class, 'index'])->name('index');
    Route::post('/', [FraudRulesController::class, 'store'])->name('store');
    Route::put('{fraudRule}', [FraudRulesController::class, 'update'])->name('update');
    Route::delete('{fraudRule}', [FraudRulesController::class, 'destroy'])->name('destroy');
    Route::post('{fraudRule}/toggle', [FraudRulesController::class, 'toggle'])->name('toggle');
});

// ── Reports & Analytics ────────────────────────────────
Route::middleware('can:fraud_detection.view_dashboard')->group(function () {
    Route::get('reports', [FraudReportsController::class, 'index'])->name('reports');
});

Route::middleware('can:fraud_detection.export_reports')->group(function () {
    Route::get('reports/export-csv', [FraudReportsController::class, 'exportCsv'])->name('reports.export-csv');
    Route::get('reports/export-excel', [FraudReportsController::class, 'exportExcel'])->name('reports.export-excel');
});

// ── Settings ───────────────────────────────────────────
Route::middleware('can:fraud_detection.manage_settings')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [FraudSettingsController::class, 'index'])->name('index');

    // Blacklisted IPs
    Route::post('blacklist/ips', [FraudSettingsController::class, 'addBlacklistedIp'])->name('blacklist.ips.store');
    Route::delete('blacklist/ips/{blacklistedIp}', [FraudSettingsController::class, 'removeBlacklistedIp'])->name('blacklist.ips.destroy');

    // Blacklisted Emails
    Route::post('blacklist/emails', [FraudSettingsController::class, 'addBlacklistedEmail'])->name('blacklist.emails.store');
    Route::delete('blacklist/emails/{blacklistedEmail}', [FraudSettingsController::class, 'removeBlacklistedEmail'])->name('blacklist.emails.destroy');

    // Country Risk
    Route::post('countries', [FraudSettingsController::class, 'saveCountryRisk'])->name('countries.store');
    Route::delete('countries/{countryRisk}', [FraudSettingsController::class, 'removeCountryRisk'])->name('countries.destroy');
});

// Alias for nav convenience
Route::get('settings', [FraudSettingsController::class, 'index'])->name('settings')->middleware('can:fraud_detection.manage_settings');
