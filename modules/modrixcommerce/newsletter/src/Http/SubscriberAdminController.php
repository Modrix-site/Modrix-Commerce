<?php

namespace EcoModular\Newsletter\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\Newsletter\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin: list, export and erase subscribers (`can:newsletter.manage`).
 */
class SubscriberAdminController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.newsletter.';

    public function index(): Response
    {
        return Inertia::render('module/modrixcommerce/newsletter/admin/index', [
            'counts' => [
                'active' => Subscriber::query()->whereNull('unsubscribed_at')->count(),
                'unsubscribed' => Subscriber::query()->whereNotNull('unsubscribed_at')->count(),
            ],
            'subscribers' => Subscriber::query()->latest('id')->limit(100)->get()->map(fn (Subscriber $s) => [
                'id' => $s->id,
                'email' => $s->email,
                'source' => $s->source,
                'consented_at' => $s->consented_at->toDateString(),
                'unsubscribed_at' => $s->unsubscribed_at?->toDateString(),
                'delete_url' => route(self::ROUTE.'destroy', $s),
            ]),
            'exportUrl' => route(self::ROUTE.'export'),
        ]);
    }

    /**
     * Active subscribers with their consent and a personal unsubscribe link,
     * for import into an email tool. Formula characters are neutralised.
     */
    public function export(AuditLogger $audit): StreamedResponse
    {
        $audit->record('newsletter.exported', null, ['count' => Subscriber::query()->whereNull('unsubscribed_at')->count()]);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['email', 'consented_at', 'consent_text', 'source', 'unsubscribe_url'], escape: '');
            Subscriber::query()->whereNull('unsubscribed_at')->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $s) {
                    /** @var Subscriber $s */
                    fputcsv($out, array_map(self::safe(...), [
                        $s->email,
                        $s->consented_at->toIso8601String(),
                        $s->consent_text,
                        $s->source,
                        route('modrixcommerce.newsletter.unsubscribe', $s->unsubscribe_token),
                    ]), escape: '');
                }
            });
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Erasure on request: the record is deleted, not just unsubscribed. */
    public function destroy(Subscriber $subscriber, AuditLogger $audit): RedirectResponse
    {
        $subscriber->delete();
        $audit->record('newsletter.subscriber_erased', null, ['subscriber' => $subscriber->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscriber erased.']);

        return back();
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
