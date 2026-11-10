<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotCaptchaChallenge extends Model
{
    protected $table = 'bot_captcha_challenges';

    protected $fillable = [
        'challenge_token',
        'ip_address',
        'user_agent',
        'session_id',
        'captcha_type',
        'status',
        'target_url',
        'attempts',
        'solved_at',
        'expires_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'solved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
