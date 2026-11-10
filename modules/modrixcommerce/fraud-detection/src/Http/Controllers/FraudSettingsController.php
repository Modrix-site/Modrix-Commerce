<?php

namespace EcoModular\FraudDetection\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\FraudDetection\Models\BlacklistedEmail;
use EcoModular\FraudDetection\Models\BlacklistedIp;
use EcoModular\FraudDetection\Models\FraudAuditLog;
use EcoModular\FraudDetection\Models\FraudCountryRisk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FraudSettingsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        $blacklistedIps = BlacklistedIp::query()
            ->with('creator')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (BlacklistedIp $ip) => [
                'id' => $ip->id,
                'ip_address' => $ip->ip_address,
                'reason' => $ip->reason,
                'is_active' => $ip->is_active,
                'is_expired' => $ip->isExpired(),
                'created_by' => $ip->creator?->name,
                'expires_at' => $ip->expires_at?->toFormattedDateString(),
                'created_at' => $ip->created_at?->toFormattedDateString(),
                'delete_url' => route('admin.modrixcommerce.fraud-detection.settings.blacklist.ips.destroy', $ip),
            ]);

        $blacklistedEmails = BlacklistedEmail::query()
            ->with('creator')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (BlacklistedEmail $e) => [
                'id' => $e->id,
                'domain' => $e->domain,
                'reason' => $e->reason,
                'is_active' => $e->is_active,
                'created_by' => $e->creator?->name,
                'created_at' => $e->created_at?->toFormattedDateString(),
                'delete_url' => route('admin.modrixcommerce.fraud-detection.settings.blacklist.emails.destroy', $e),
            ]);

        $countryRisks = FraudCountryRisk::query()
            ->where('is_active', true)
            ->orderBy('country_name')
            ->get()
            ->map(fn (FraudCountryRisk $c) => [
                'id' => $c->id,
                'country_code' => $c->country_code,
                'country_name' => $c->country_name,
                'risk_level' => $c->risk_level,
                'score_modifier' => $c->score_modifier,
                'delete_url' => route('admin.modrixcommerce.fraud-detection.settings.countries.destroy', $c),
            ]);

        $auditLogs = FraudAuditLog::query()
            ->with('user')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (FraudAuditLog $log) => [
                'id' => $log->id,
                'user_name' => $log->user?->name ?? 'System',
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->diffForHumans(),
            ]);

        return Inertia::render('module/modrixcommerce/fraud-detection/admin/settings', [
            'blacklistedIps' => $blacklistedIps,
            'blacklistedEmails' => $blacklistedEmails,
            'countryRisks' => $countryRisks,
            'auditLogs' => $auditLogs,
            'addIpUrl' => route('admin.modrixcommerce.fraud-detection.settings.blacklist.ips.store'),
            'addEmailUrl' => route('admin.modrixcommerce.fraud-detection.settings.blacklist.emails.store'),
            'addCountryUrl' => route('admin.modrixcommerce.fraud-detection.settings.countries.store'),
        ]);
    }

    public function addBlacklistedIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:fraud_blacklisted_ips,ip_address',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $ip = BlacklistedIp::create([
            ...$validated,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        FraudAuditLog::record('blacklist.ip_added', 'blacklisted_ip', $ip->id, [
            'ip_address' => $ip->ip_address,
        ]);

        $this->audit->record('fraud.ip_blacklisted', null, ['ip_address' => $ip->ip_address]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "IP {$ip->ip_address} blacklisted."]);

        return back();
    }

    public function removeBlacklistedIp(BlacklistedIp $blacklistedIp): RedirectResponse
    {
        $ipAddress = $blacklistedIp->ip_address;
        $id = $blacklistedIp->id;
        $blacklistedIp->delete();

        FraudAuditLog::record('blacklist.ip_removed', 'blacklisted_ip', $id, [
            'ip_address' => $ipAddress,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "IP {$ipAddress} removed from blacklist."]);

        return back();
    }

    public function addBlacklistedEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:191|unique:fraud_blacklisted_emails,domain',
            'reason' => 'nullable|string|max:255',
        ]);

        $email = BlacklistedEmail::create([
            'domain' => strtolower($validated['domain']),
            'reason' => $validated['reason'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        FraudAuditLog::record('blacklist.email_added', 'blacklisted_email', $email->id, [
            'domain' => $email->domain,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Domain \"{$email->domain}\" blacklisted."]);

        return back();
    }

    public function removeBlacklistedEmail(BlacklistedEmail $blacklistedEmail): RedirectResponse
    {
        $domain = $blacklistedEmail->domain;
        $id = $blacklistedEmail->id;
        $blacklistedEmail->delete();

        FraudAuditLog::record('blacklist.email_removed', 'blacklisted_email', $id, [
            'domain' => $domain,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Domain \"{$domain}\" removed from blacklist."]);

        return back();
    }

    public function saveCountryRisk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_code' => 'required|string|size:2',
            'country_name' => 'required|string|max:100',
            'risk_level' => 'required|string|in:low,medium,high,blocked',
            'score_modifier' => 'required|integer|min:0|max:100',
        ]);

        $country = FraudCountryRisk::updateOrCreate(
            ['country_code' => strtoupper($validated['country_code'])],
            [
                'country_name' => $validated['country_name'],
                'risk_level' => $validated['risk_level'],
                'score_modifier' => $validated['score_modifier'],
                'is_active' => true,
            ]
        );

        FraudAuditLog::record('country_risk.saved', 'fraud_country_risk', $country->id, [
            'country_code' => $country->country_code,
            'risk_level' => $country->risk_level,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Country risk for {$country->country_name} saved."]);

        return back();
    }

    public function removeCountryRisk(FraudCountryRisk $countryRisk): RedirectResponse
    {
        $name = $countryRisk->country_name;
        $countryRisk->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Country risk for {$name} removed."]);

        return back();
    }
}
