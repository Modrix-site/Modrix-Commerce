<?php

namespace EcoModular\LoginMonitoring\Models;

use Illuminate\Database\Eloquent\Model;

class LoginFailedAttempt extends Model
{
    protected $table = 'login_failed_attempts';

    protected $fillable = [
        'email',
        'ip_address',
        'attempt_count',
        'last_attempt_at',
        'is_locked_out',
        'locked_until',
        'lockout_reason',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'is_locked_out' => 'boolean',
        'last_attempt_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    public function isLockedOutActive(): bool
    {
        return $this->is_locked_out && $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
