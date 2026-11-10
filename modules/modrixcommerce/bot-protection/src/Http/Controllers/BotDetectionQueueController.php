<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotAllowedIp;
use EcoModular\BotProtection\Models\BotAuditLog;
use EcoModular\BotProtection\Models\BotBlockedIp;
use EcoModular\BotProtection\Models\BotEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotDetectionQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $typeFilter = $request->query('type', 'all');
        $minScore = (int) $request->query('min_score', 0);

        $query = BotEvent::query()->where('is_resolved', false)->orderByDesc('created_at');

        if ($typeFilter !== 'all') {
            $query->where('bot_type', $typeFilter);
        }

        if ($minScore > 0) {
            $query->where('risk_score', '>=', $minScore);
        }

        $events = $query->paginate(20)->withQueryString();

        return Inertia::render('module/modrixcommerce/bot-protection/admin/detection-queue', [
            'events' => $events,
            'filters' => [
                'type' => $typeFilter,
                'min_score' => $minScore,
            ],
            'pendingCount' => BotEvent::query()->where('is_resolved', false)->count(),
        ]);
    }

    public function resolve(Request $request, BotEvent $event): RedirectResponse
    {
        $event->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => auth()->user()?->name ?? 'Admin',
            'resolution_notes' => $request->input('notes', 'Resolved manually by admin'),
        ]);

        BotAuditLog::record("Resolved Bot Event #{$event->id}", 'bot_event', (string) $event->id);

        return back()->with('success', "Event #{$event->id} marked as resolved.");
    }

    public function blockIp(Request $request, BotEvent $event): RedirectResponse
    {
        $ip = $event->ip_address;

        BotBlockedIp::updateOrCreate(
            ['ip_address' => $ip],
            [
                'reason' => "Blocked from Bot Queue (Event #{$event->id}: {$event->bot_type})",
                'attack_type' => $event->bot_type,
                'threat_level' => $event->threat_level,
                'auto_blocked' => false,
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]
        );

        $event->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => auth()->user()?->name ?? 'Admin',
            'resolution_notes' => "Blocked IP address {$ip}",
        ]);

        BotAuditLog::record("Blocked IP {$ip} from Queue", 'bot_blocked_ip', $ip);

        return back()->with('success', "IP address {$ip} has been added to the Blacklist.");
    }

    public function whitelistIp(Request $request, BotEvent $event): RedirectResponse
    {
        $ip = $event->ip_address;

        BotAllowedIp::updateOrCreate(
            ['ip_address' => $ip],
            [
                'label' => "Whitelisted from Queue (Event #{$event->id})",
                'description' => "Marked as trusted visitor by " . (auth()->user()?->name ?? 'Admin'),
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]
        );

        $event->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => auth()->user()?->name ?? 'Admin',
            'resolution_notes' => "Whitelisted IP address {$ip}",
        ]);

        BotAuditLog::record("Whitelisted IP {$ip} from Queue", 'bot_allowed_ip', $ip);

        return back()->with('success', "IP address {$ip} has been whitelisted.");
    }
}
