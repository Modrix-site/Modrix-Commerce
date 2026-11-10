<?php

namespace EcoModular\LoginMonitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\LoginMonitoring\Models\LoginAuditLog;
use EcoModular\LoginMonitoring\Models\LoginSecurityIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginIncidentsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $severity = $request->input('severity');
        $type = $request->input('type');

        $query = LoginSecurityIncident::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($status) && in_array($status, ['open', 'investigating', 'resolved', 'dismissed'])) {
            $query->where('status', $status);
        }

        if (!empty($severity) && in_array($severity, ['low', 'medium', 'high', 'critical'])) {
            $query->where('severity', $severity);
        }

        if (!empty($type)) {
            $query->where('incident_type', $type);
        }

        $incidents = $query->paginate(20)->withQueryString()->through(fn ($inc) => [
            'id' => $inc->id,
            'uuid' => $inc->incident_uuid,
            'incident_type' => $inc->incident_type,
            'user_id' => $inc->user_id,
            'user_name' => $inc->user?->name,
            'email' => $inc->email,
            'ip_address' => $inc->ip_address,
            'severity' => $inc->severity,
            'status' => $inc->status,
            'description' => $inc->description,
            'metadata' => $inc->metadata ?? [],
            'resolved_by' => $inc->resolved_by,
            'resolved_at' => $inc->resolved_at?->format('Y-m-d H:i'),
            'resolution_notes' => $inc->resolution_notes,
            'created_at' => $inc->created_at?->diffForHumans() ?? 'Just now',
            'timestamp' => $inc->created_at?->format('Y-m-d H:i:s'),
        ]);

        $summary = [
            'open' => LoginSecurityIncident::where('status', 'open')->count(),
            'investigating' => LoginSecurityIncident::where('status', 'investigating')->count(),
            'resolved' => LoginSecurityIncident::where('status', 'resolved')->count(),
            'critical' => LoginSecurityIncident::where('severity', 'critical')->count(),
        ];

        return Inertia::render('module/modrixcommerce/login-monitoring/admin/incidents', [
            'incidents' => $incidents,
            'summary' => $summary,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
                'severity' => $severity ?? '',
                'type' => $type ?? '',
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,investigating,resolved,dismissed'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $incident = LoginSecurityIncident::findOrFail($id);

        $incident->update([
            'status' => $validated['status'],
            'resolution_notes' => $validated['resolution_notes'] ?? $incident->resolution_notes,
            'resolved_by' => in_array($validated['status'], ['resolved', 'dismissed']) ? (auth()->user()?->name ?? 'Admin') : null,
            'resolved_at' => in_array($validated['status'], ['resolved', 'dismissed']) ? now() : null,
        ]);

        LoginAuditLog::record(
            "Updated incident #{$incident->id} status to {$validated['status']}",
            'security_incident',
            (string) $incident->id,
            ['status' => $validated['status'], 'notes' => $validated['resolution_notes'] ?? null]
        );

        return back()->with('success', "Incident #{$incident->id} marked as {$validated['status']}.");
    }
}
