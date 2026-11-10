<?php

namespace EcoModular\LoginMonitoring\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginMfaConfiguration extends Model
{
    protected $table = 'login_mfa_configurations';

    protected $fillable = [
        'user_id',
        'mfa_enabled',
        'mfa_type',
        'secret_key',
        'recovery_codes',
        'phone_number',
        'last_used_at',
    ];

    protected $casts = [
        'mfa_enabled' => 'boolean',
        'recovery_codes' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
