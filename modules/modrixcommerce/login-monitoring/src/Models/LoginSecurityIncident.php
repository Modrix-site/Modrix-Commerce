<?php

namespace EcoModular\LoginMonitoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSecurityIncident extends Model
{
    protected $table = 'login_security_incidents';

    protected $fillable = [
        'incident_uuid',
        'incident_type',
        'user_id',
        'email',
        'ip_address',
        'severity',
        'status',
        'description',
        'metadata',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
