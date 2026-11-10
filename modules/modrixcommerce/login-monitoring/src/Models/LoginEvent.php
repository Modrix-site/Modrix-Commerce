<?php

namespace EcoModular\LoginMonitoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    protected $table = 'login_events';

    public $timestamps = false;

    protected $fillable = [
        'event_uuid',
        'user_id',
        'email',
        'ip_address',
        'status',
        'failure_reason',
        'risk_score',
        'threat_level',
        'device_fingerprint',
        'device_type',
        'browser',
        'operating_system',
        'user_agent',
        'country_code',
        'city',
        'latitude',
        'longitude',
        'is_suspicious',
        'suspicious_reasons',
        'session_id',
        'created_at',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'is_suspicious' => 'boolean',
        'suspicious_reasons' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
