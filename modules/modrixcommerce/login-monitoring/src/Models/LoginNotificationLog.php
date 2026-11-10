<?php

namespace EcoModular\LoginMonitoring\Models;

use Illuminate\Database\Eloquent\Model;

class LoginNotificationLog extends Model
{
    protected $table = 'login_notification_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'channel',
        'recipient',
        'event_type',
        'subject',
        'status',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
