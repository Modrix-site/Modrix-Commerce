<?php

namespace EcoModular\LoginMonitoring\Models;

use Illuminate\Database\Eloquent\Model;

class LoginBlockedIp extends Model
{
    protected $table = 'login_blocked_ips';

    protected $fillable = [
        'ip_address',
        'cidr_mask',
        'reason',
        'attack_type',
        'auto_blocked',
        'hit_count',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'cidr_mask' => 'integer',
        'auto_blocked' => 'boolean',
        'hit_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
