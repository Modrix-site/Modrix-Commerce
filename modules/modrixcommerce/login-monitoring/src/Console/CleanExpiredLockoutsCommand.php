<?php

namespace EcoModular\LoginMonitoring\Console;

use EcoModular\LoginMonitoring\Models\LoginFailedAttempt;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use Illuminate\Console\Command;

class CleanExpiredLockoutsCommand extends Command
{
    protected $signature = 'login-monitoring:clean-expired';

    protected $description = 'Clean expired lockouts, temporary blocks, and expired sessions';

    public function handle(): int
    {
        $lockouts = LoginFailedAttempt::query()
            ->where('is_locked_out', true)
            ->where('locked_until', '<', now())
            ->update([
                'is_locked_out' => false,
                'attempt_count' => 0,
            ]);

        $sessions = LoginUserSession::query()
            ->where('expires_at', '<', now())
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);

        $this->info("Cleaned {$lockouts} expired lockouts and updated {$sessions} expired sessions.");

        return 0;
    }
}
