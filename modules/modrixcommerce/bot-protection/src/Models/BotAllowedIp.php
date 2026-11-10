<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotAllowedIp extends Model
{
    protected $table = 'bot_allowed_ips';

    protected $fillable = [
        'ip_address',
        'cidr_mask',
        'label',
        'description',
        'is_verified_crawler',
        'created_by',
    ];

    protected $casts = [
        'cidr_mask' => 'integer',
        'is_verified_crawler' => 'boolean',
    ];
}
