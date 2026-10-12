<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_.-]*$/'],
            'actor' => ['nullable', 'string', 'max:191'],
        ]);

        $logs = AuditLog::query()
            ->when($filters['action'] ?? null, fn ($q, string $action) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($filters['actor'] ?? null, fn ($q, string $actor) => $q->where('actor_label', 'like', '%'.addcslashes($actor, '%_\\').'%'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/audit-logs/index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => [
                'action' => $filters['action'] ?? '',
                'actor' => $filters['actor'] ?? '',
            ],
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
