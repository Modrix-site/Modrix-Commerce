<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotTrafficLog extends Model
{
    protected $table = 'bot_traffic_logs';

    protected $fillable = [
        'ip_address',
        'request_uri',
        'method',
        'user_agent',
        'status_code',
        'response_time_ms',
        'bot_score',
        'is_bot',
        'classification',
        'action',
        'country_code',
        'referer',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'bot_score' => 'integer',
        'is_bot' => 'boolean',
    ];
}
