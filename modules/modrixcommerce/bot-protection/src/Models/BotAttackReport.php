<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotAttackReport extends Model
{
    protected $table = 'bot_attack_reports';

    protected $fillable = [
        'report_date',
        'total_requests',
        'human_requests',
        'bot_requests',
        'blocked_requests',
        'challenged_requests',
        'solved_challenges',
        'failed_challenges',
        'scraping_attempts',
        'credential_stuffing_attempts',
        'checkout_abuse_attempts',
        'avg_bot_score',
        'top_threat_types',
        'top_attacking_ips',
        'top_target_paths',
        'top_countries',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_requests' => 'integer',
        'human_requests' => 'integer',
        'bot_requests' => 'integer',
        'blocked_requests' => 'integer',
        'challenged_requests' => 'integer',
        'solved_challenges' => 'integer',
        'failed_challenges' => 'integer',
        'scraping_attempts' => 'integer',
        'credential_stuffing_attempts' => 'integer',
        'checkout_abuse_attempts' => 'integer',
        'avg_bot_score' => 'float',
        'top_threat_types' => 'array',
        'top_attacking_ips' => 'array',
        'top_target_paths' => 'array',
        'top_countries' => 'array',
    ];
}
