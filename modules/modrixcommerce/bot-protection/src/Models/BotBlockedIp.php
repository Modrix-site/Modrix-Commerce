<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotBlockedIp extends Model
{
    protected $table = 'bot_blocked_ips';

    protected $fillable = [
        'ip_address',
        'cidr_mask',
        'reason',
        'attack_type',
        'threat_level',
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
