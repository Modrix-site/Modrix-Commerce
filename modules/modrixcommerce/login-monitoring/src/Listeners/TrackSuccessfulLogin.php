<?php

namespace EcoModular\LoginMonitoring\Listeners;

use EcoModular\LoginMonitoring\Services\LoginTrackerService;
use Illuminate\Auth\Events\Login;

class TrackSuccessfulLogin
{
    public function __construct(
        private readonly LoginTrackerService $tracker,
    ) {}

    public function handle(Login $event): void
    {
        if ($event->user) {
            $this->tracker->recordSuccess($event->user, request());
        }
    }
}
