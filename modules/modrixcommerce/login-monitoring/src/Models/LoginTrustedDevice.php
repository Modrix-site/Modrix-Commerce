<?php

namespace EcoModular\LoginMonitoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginTrustedDevice extends Model
{
    protected $table = 'login_trusted_devices';

    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_name',
        'browser',
        'operating_system',
        'ip_address',
        'last_used_at',
        'is_revoked',
    ];

    protected $casts = [
        'is_revoked' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
