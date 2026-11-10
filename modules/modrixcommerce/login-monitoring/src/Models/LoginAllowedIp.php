<?php

namespace EcoModular\LoginMonitoring\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAllowedIp extends Model
{
    protected $table = 'login_allowed_ips';

    protected $fillable = [
        'ip_address',
        'cidr_mask',
        'label',
        'description',
        'created_by',
    ];

    protected $casts = [
        'cidr_mask' => 'integer',
    ];
}
