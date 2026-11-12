<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Support\Like;
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
            // The action filter is validated to [a-z0-9_.-], so only "_" needs neutralising.
            ->when($filters['action'] ?? null, fn ($q, string $action) => $q->whereRaw("action LIKE ? ESCAPE '!'", [str_replace('_', '!_', $action).'%']))
            ->when($filters['actor'] ?? null, fn ($q, string $actor) => Like::where($q, 'actor_label', $actor))
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
