<?php

namespace EcoModular\LoginMonitoring\Listeners;

use EcoModular\LoginMonitoring\Services\LoginTrackerService;
use Illuminate\Auth\Events\Failed;

class TrackFailedLogin
{
    public function __construct(
        private readonly LoginTrackerService $tracker,
    ) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown@user.com';
        $this->tracker->recordFailed((string) $email, request(), 'Invalid password or unknown account');
    }
}
