<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginAuditLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $action = $request->input('action');

        $query = LoginAuditLog::query()->orderByDesc('created_at');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('target_id', 'like', "%{$search}%");
            });
        }

        if (! empty($action)) {
            $query->where('action', 'like', "%{$action}%");
        }

        $logs = $query->paginate(25)->withQueryString()->through(fn ($log) => [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_name' => $log->user_name,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'details' => $log->details,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->diffForHumans() ?? 'Just now',
            'timestamp' => $log->created_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/audit-logs', [
            'logs' => $logs,
            'filters' => [
                'search' => $search ?? '',
                'action' => $action ?? '',
            ],
        ]);
    }
}
