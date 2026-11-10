<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use EcoModular\LoginMonitoring\Services\SessionManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginSessionsController extends Controller
{
    public function __construct(
        private readonly SessionManagerService $sessionManager,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status', 'active'); // 'active', 'revoked', 'all'

        $query = LoginUserSession::query()
            ->with('user:id,name,email')
            ->orderByDesc('last_activity');

        if ($status === 'active') {
            $query->where('is_revoked', false)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        } elseif ($status === 'revoked') {
            $query->where('is_revoked', true);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $sessions = $query->paginate(20)->withQueryString()->through(fn ($s) => [
            'id' => $s->id,
            'session_id' => $s->session_id,
            'user_id' => $s->user_id,
            'user_name' => $s->user?->name ?? 'Guest/Unknown',
            'user_email' => $s->user?->email,
            'ip_address' => $s->ip_address,
            'device_type' => $s->device_type,
            'browser' => $s->browser,
            'operating_system' => $s->operating_system,
            'country_code' => $s->country_code,
            'city' => $s->city,
            'is_current' => (bool) $s->is_current,
            'is_revoked' => (bool) $s->is_revoked,
            'last_activity' => $s->last_activity?->diffForHumans() ?? 'Never',
            'expires_at' => $s->expires_at?->format('Y-m-d H:i') ?? 'None',
        ]);

        $activeCount = LoginUserSession::query()
            ->where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/sessions', [
            'sessions' => $sessions,
            'activeCount' => $activeCount,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status,
            ],
        ]);
    }

    public function revoke(string $sessionId): RedirectResponse
    {
        $revoked = $this->sessionManager->revokeSession($sessionId);

        if ($revoked) {
            return back()->with('success', 'User session has been forcefully terminated.');
        }

        return back()->with('error', 'Session not found or already terminated.');
    }

    public function revokeAll(int $userId): RedirectResponse
    {
        $count = $this->sessionManager->revokeAllUserSessions($userId);

        return back()->with('success', "Terminated {$count} active sessions for the selected user.");
    }
}
