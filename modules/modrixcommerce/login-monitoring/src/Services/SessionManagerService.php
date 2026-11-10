<?php

namespace EcoModular\LoginMonitoring\Services;

use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use Illuminate\Support\Facades\DB;

class SessionManagerService
{
    public function getActiveSessions(int $limit = 50)
    {
        return LoginUserSession::query()
            ->with('user:id,name,email')
            ->where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_activity')
            ->paginate($limit);
    }

    public function revokeSession(string $sessionId): bool
    {
        $session = LoginUserSession::query()->where('session_id', $sessionId)->first();
        if (!$session) {
            return false;
        }

        $session->update([
            'is_revoked' => true,
        ]);

        // Delete from Laravel framework sessions table if database session driver is used
        try {
            DB::table('sessions')->where('id', $sessionId)->delete();
        } catch (\Throwable) {
            // Ignore if file/cache driver is used
        }

        LoginAuditLog::record("Revoked user session: {$sessionId}", 'user_session', $sessionId);

        return true;
    }

    public function revokeAllUserSessions(int $userId): int
    {
        $count = LoginUserSession::query()
            ->where('user_id', $userId)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        LoginAuditLog::record("Revoked all ({$count}) active sessions for User #{$userId}", 'user', (string) $userId);

        return $count;
    }
}
