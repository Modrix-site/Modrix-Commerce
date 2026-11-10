<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotEvent extends Model
{
    protected $table = 'bot_events';

    protected $fillable = [
        'event_uuid',
        'ip_address',
        'user_agent',
        'request_path',
        'request_method',
        'bot_type',
        'risk_score',
        'threat_level',
        'action_taken',
        'reasons',
        'request_headers',
        'request_payload',
        'session_id',
        'user_id',
        'country_code',
        'city',
        'asn',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'reasons' => 'array',
        'request_headers' => 'array',
        'request_payload' => 'array',
        'risk_score' => 'integer',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];
}
