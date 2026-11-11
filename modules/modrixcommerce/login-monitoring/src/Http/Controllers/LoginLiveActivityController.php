<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginBlockedIp;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginLiveActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $threatLevel = $request->input('threat_level');

        $query = LoginEvent::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country_code', 'like', "%{$search}%");
            });
        }

        if (! empty($status) && in_array($status, ['success', 'failed'])) {
            $query->where('status', $status);
        }

        if (! empty($threatLevel) && in_array($threatLevel, ['low', 'medium', 'high', 'critical'])) {
            $query->where('threat_level', $threatLevel);
        }

        $events = $query->paginate(25)->withQueryString()->through(fn ($e) => [
            'id' => $e->id,
            'uuid' => $e->event_uuid,
            'email' => $e->email,
            'user_name' => $e->user?->name,
            'ip_address' => $e->ip_address,
            'status' => $e->status,
            'failure_reason' => $e->failure_reason,
            'risk_score' => $e->risk_score,
            'threat_level' => $e->threat_level,
            'device_type' => $e->device_type,
            'browser' => $e->browser,
            'operating_system' => $e->operating_system,
            'user_agent' => $e->user_agent,
            'country_code' => $e->country_code,
            'city' => $e->city,
            'is_suspicious' => (bool) $e->is_suspicious,
            'suspicious_reasons' => $e->suspicious_reasons ?? [],
            'created_at' => $e->created_at?->diffForHumans() ?? 'Just now',
            'timestamp' => $e->created_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/live-activity', [
            'events' => $events,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
                'threat_level' => $threatLevel ?? '',
            ],
        ]);
    }

    public function blockIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        LoginBlockedIp::updateOrCreate(
            ['ip_address' => $validated['ip_address']],
            [
                'reason' => $validated['reason'] ?? 'Blocked directly from Live Activity monitor',
                'attack_type' => 'manual',
                'auto_blocked' => false,
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]
        );

        LoginAuditLog::record(
            "Blocked IP: {$validated['ip_address']}",
            'blocked_ip',
            $validated['ip_address'],
            ['reason' => $validated['reason'] ?? 'Manual block']
        );

        return back()->with('success', "IP {$validated['ip_address']} has been added to blocked IPs.");
    }
}
