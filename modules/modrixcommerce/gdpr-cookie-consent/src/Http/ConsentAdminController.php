<?php

namespace EcoModular\GdprCookieConsent\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\GdprCookieConsent\Models\ConsentRecord;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin: the consent decisions the banner collected (`can:privacy.manage`).
 */
class ConsentAdminController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.gdpr-cookie-consent.';

    public function index(): Response
    {
        $records = ConsentRecord::query()->latest('id')->limit(100)->get();

        $counts = [
            'total' => ConsentRecord::query()->count(),
            'accepted' => ConsentRecord::query()->where('decision', 'accept')->count(),
            'rejected' => ConsentRecord::query()->where('decision', 'reject')->count(),
            'customised' => ConsentRecord::query()->where('decision', 'customize')->count(),
        ];

        return Inertia::render('module/modrixcommerce/gdpr-cookie-consent/admin/index', [
            'counts' => $counts,
            'records' => $records->map(fn (ConsentRecord $r) => [
                'id' => $r->id,
                'decision' => $r->decision,
                'analytics_allowed' => $r->analytics_allowed,
                'marketing_allowed' => $r->marketing_allowed,
                'country_code' => $r->country_code,
                'page' => $r->page,
                'decided_at' => $r->decided_at->toDateTimeString(),
                'delete_url' => route(self::ROUTE.'destroy', $r),
            ]),
            'exportUrl' => route(self::ROUTE.'export'),
        ]);
    }

    /**
     * Consent decisions for your records. Formula characters are neutralised.
     */
    public function export(AuditLogger $audit): StreamedResponse
    {
        $audit->record('gdpr_consent.exported', null, ['count' => ConsentRecord::query()->count()]);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['id', 'decision', 'analytics_allowed', 'marketing_allowed', 'country_code', 'page', 'decided_at'], escape: '');
            ConsentRecord::query()->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $record) {
                    /** @var ConsentRecord $record */
                    fputcsv($out, array_map(self::safe(...), [
                        (string) $record->id,
                        $record->decision,
                        $record->analytics_allowed ? 'yes' : 'no',
                        $record->marketing_allowed ? 'yes' : 'no',
                        (string) $record->country_code,
                        (string) $record->page,
                        $record->decided_at->toIso8601String(),
                    ]), escape: '');
                }
            });
            fclose($out);
        }, 'gdpr-consent-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Erasure on request: the record is deleted. */
    public function destroy(ConsentRecord $record, AuditLogger $audit): RedirectResponse
    {
        $record->delete();
        $audit->record('gdpr_consent.erased', null, ['record' => $record->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Consent record erased.']);

        return back();
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
