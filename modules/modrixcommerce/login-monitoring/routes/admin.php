<?php

use EcoModular\LoginMonitoring\Http\Controllers\LoginAuditLogsController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginDashboardController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginIncidentsController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginLiveActivityController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginMfaSettingsController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginPoliciesController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginReportsController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginSessionsController;
use EcoModular\LoginMonitoring\Http\Controllers\LoginSuspiciousQueueController;
use Illuminate\Support\Facades\Route;

// ── Dashboard ──────────────────────────────────────────
Route::middleware('can:login_monitoring.view_dashboard')->group(function () {
    Route::get('/', [LoginDashboardController::class, 'index'])->name('dashboard');
});

// ── Live Activity ──────────────────────────────────────
Route::middleware('can:login_monitoring.view_dashboard')->prefix('activity')->name('activity.')->group(function () {
    Route::get('/', [LoginLiveActivityController::class, 'index'])->name('index');
    Route::post('/block-ip', [LoginLiveActivityController::class, 'blockIp'])->name('block-ip');
});

// ── Suspicious Queue ───────────────────────────────────
Route::middleware('can:login_monitoring.manage_incidents')->prefix('queue')->name('queue.')->group(function () {
    Route::get('/', [LoginSuspiciousQueueController::class, 'index'])->name('index');
    Route::post('/{id}/dismiss', [LoginSuspiciousQueueController::class, 'dismiss'])->name('dismiss');
    Route::post('/{id}/force-logout', [LoginSuspiciousQueueController::class, 'forceLogout'])->name('force-logout');
    Route::post('/whitelist-ip', [LoginSuspiciousQueueController::class, 'whitelistIp'])->name('whitelist-ip');
});

// ── Active Sessions ────────────────────────────────────
Route::middleware('can:login_monitoring.manage_sessions')->prefix('sessions')->name('sessions.')->group(function () {
    Route::get('/', [LoginSessionsController::class, 'index'])->name('index');
    Route::post('/{sessionId}/revoke', [LoginSessionsController::class, 'revoke'])->name('revoke');
    Route::post('/revoke-all/{userId}', [LoginSessionsController::class, 'revokeAll'])->name('revoke-all');
});

// ── Security Incidents ─────────────────────────────────
Route::middleware('can:login_monitoring.manage_incidents')->prefix('incidents')->name('incidents.')->group(function () {
    Route::get('/', [LoginIncidentsController::class, 'index'])->name('index');
    Route::post('/{id}/status', [LoginIncidentsController::class, 'updateStatus'])->name('update-status');
});

// ── Reports & Analytics ────────────────────────────────
Route::middleware('can:login_monitoring.view_dashboard')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [LoginReportsController::class, 'index'])->name('index');
});

Route::middleware('can:login_monitoring.export_reports')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/export/csv', [LoginReportsController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export/excel', [LoginReportsController::class, 'exportExcel'])->name('export.excel');
});

// ── MFA Settings ───────────────────────────────────────
Route::middleware('can:login_monitoring.manage_policies')->prefix('mfa')->name('mfa.')->group(function () {
    Route::get('/', [LoginMfaSettingsController::class, 'index'])->name('index');
    Route::post('/policy', [LoginMfaSettingsController::class, 'updatePolicy'])->name('update-policy');
    Route::post('/reset/{userId}', [LoginMfaSettingsController::class, 'resetUserMfa'])->name('reset');
});

// ── Access Policies & IP Management ────────────────────
Route::middleware('can:login_monitoring.manage_policies')->prefix('policies')->name('policies.')->group(function () {
    Route::get('/', [LoginPoliciesController::class, 'index'])->name('index');
    Route::post('/settings', [LoginPoliciesController::class, 'updateSettings'])->name('settings');
    Route::post('/allowed-ips', [LoginPoliciesController::class, 'storeAllowedIp'])->name('allowed.store');
    Route::delete('/allowed-ips/{id}', [LoginPoliciesController::class, 'destroyAllowedIp'])->name('allowed.destroy');
    Route::post('/blocked-ips', [LoginPoliciesController::class, 'storeBlockedIp'])->name('blocked.store');
    Route::delete('/blocked-ips/{id}', [LoginPoliciesController::class, 'destroyBlockedIp'])->name('blocked.destroy');
});

// ── Audit Logs ─────────────────────────────────────────
Route::middleware('can:login_monitoring.view_dashboard')->prefix('audit')->name('audit.')->group(function () {
    Route::get('/', [LoginAuditLogsController::class, 'index'])->name('index');
});
