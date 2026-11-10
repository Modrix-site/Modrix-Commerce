<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotScore extends Model
{
    protected $table = 'bot_scores';

    protected $fillable = [
        'ip_address',
        'session_id',
        'fingerprint_hash',
        'overall_score',
        'threat_level',
        'total_requests',
        'violation_count',
        'heuristic_factors',
        'country_code',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'overall_score' => 'integer',
        'total_requests' => 'integer',
        'violation_count' => 'integer',
        'heuristic_factors' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
