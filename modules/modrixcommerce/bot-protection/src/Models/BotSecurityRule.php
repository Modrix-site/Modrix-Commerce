<?php

namespace EcoModular\BotProtection\Models;

use Illuminate\Database\Eloquent\Model;

class BotSecurityRule extends Model
{
    protected $table = 'bot_security_rules';

    protected $fillable = [
        'name',
        'description',
        'rule_type',
        'conditions',
        'action',
        'priority',
        'is_active',
        'hits_count',
    ];

    protected $casts = [
        'conditions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'hits_count' => 'integer',
    ];
}
