<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginAllowedIp;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Services\SessionManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginSuspiciousQueueController extends Controller
{
    public function __construct(
        private readonly SessionManagerService $sessionManager,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $query = LoginEvent::query()
            ->with('user:id,name,email')
            ->where(function ($q) {
                $q->where('is_suspicious', true)
                    ->orWhere('risk_score', '>=', 30);
            })
            ->orderByDesc('created_at');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $queue = $query->paginate(20)->withQueryString()->through(fn ($e) => [
            'id' => $e->id,
            'uuid' => $e->event_uuid,
            'user_id' => $e->user_id,
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
            'country_code' => $e->country_code,
            'city' => $e->city,
            'suspicious_reasons' => $e->suspicious_reasons ?? [],
            'created_at' => $e->created_at?->diffForHumans() ?? 'Just now',
            'timestamp' => $e->created_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/suspicious-queue', [
            'queue' => $queue,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    public function dismiss(int $id): RedirectResponse
    {
        $event = LoginEvent::findOrFail($id);
        $event->update([
            'is_suspicious' => false,
        ]);

        LoginAuditLog::record(
            "Dismissed suspicious flag for Login Event #{$event->id}",
            'login_event',
            (string) $event->id
        );

        return back()->with('success', 'Event dismissed from suspicious queue.');
    }

    public function forceLogout(Request $request, int $id): RedirectResponse
    {
        $event = LoginEvent::findOrFail($id);

        if ($event->user_id) {
            $this->sessionManager->revokeAllUserSessions($event->user_id);

            return back()->with('success', "Force-logged out all active sessions for {$event->email}.");
        }

        return back()->with('error', 'Cannot force logout an unauthenticated or unknown user.');
    }

    public function whitelistIp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        LoginAllowedIp::updateOrCreate(
            ['ip_address' => $validated['ip_address']],
            [
                'label' => $validated['label'] ?? 'Whitelisted from Suspicious Queue',
                'description' => 'Marked as safe by administrator',
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]
        );

        LoginAuditLog::record(
            "Whitelisted IP: {$validated['ip_address']}",
            'allowed_ip',
            $validated['ip_address']
        );

        return back()->with('success', "IP {$validated['ip_address']} has been added to allowed list.");
    }
}
