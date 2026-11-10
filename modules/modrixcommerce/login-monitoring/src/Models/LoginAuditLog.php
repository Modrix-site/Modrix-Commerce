<?php

namespace EcoModular\LoginMonitoring\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAuditLog extends Model
{
    protected $table = 'login_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'target_type',
        'target_id',
        'details',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $details = null
    ): self {
        $user = auth()->user();

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
