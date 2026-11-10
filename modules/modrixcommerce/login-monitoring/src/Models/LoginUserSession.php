<?php

namespace EcoModular\LoginMonitoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginUserSession extends Model
{
    protected $table = 'login_user_sessions';

    protected $fillable = [
        'session_id',
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'operating_system',
        'country_code',
        'city',
        'is_current',
        'is_revoked',
        'last_activity',
        'expires_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'is_revoked' => 'boolean',
        'last_activity' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
