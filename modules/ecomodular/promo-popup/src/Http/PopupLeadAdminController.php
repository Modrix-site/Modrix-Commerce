<?php

namespace EcoModular\PromoPopup\Http;

use App\Core\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use EcoModular\PromoPopup\Models\PopupLead;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin: the leads the pop-up collected (`can:marketing.manage`).
 */
class PopupLeadAdminController extends Controller
{
    private const ROUTE = 'admin.ecomodular.promo-popup.';

    public function index(): Response
    {
        return Inertia::render('module/ecomodular/promo-popup/admin/index', [
            'counts' => [
                'leads' => PopupLead::query()->count(),
                'with_coupon' => PopupLead::query()->whereNotNull('coupon_code')->count(),
            ],
            'leads' => PopupLead::query()->latest('id')->limit(100)->get()->map(fn (PopupLead $lead) => [
                'id' => $lead->id,
                'email' => $lead->email,
                'coupon_code' => $lead->coupon_code,
                'page' => $lead->page,
                'consented_at' => $lead->consented_at->toDateString(),
                'delete_url' => route(self::ROUTE.'destroy', $lead),
            ]),
            'exportUrl' => route(self::ROUTE.'export'),
        ]);
    }

    /**
     * Leads with their consent, for import into an email tool. Formula
     * characters are neutralised.
     */
    public function export(AuditLogger $audit): StreamedResponse
    {
        $audit->record('promo_popup.exported', null, ['count' => PopupLead::query()->count()]);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['email', 'coupon_code', 'page', 'consented_at', 'consent_text'], escape: '');
            PopupLead::query()->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $lead) {
                    /** @var PopupLead $lead */
                    fputcsv($out, array_map(self::safe(...), [
                        $lead->email,
                        (string) $lead->coupon_code,
                        (string) $lead->page,
                        $lead->consented_at->toIso8601String(),
                        $lead->consent_text,
                    ]), escape: '');
                }
            });
            fclose($out);
        }, 'marketing-popup-leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Erasure on request: the record is deleted. */
    public function destroy(PopupLead $lead, AuditLogger $audit): RedirectResponse
    {
        $lead->delete();
        $audit->record('promo_popup.lead_erased', null, ['lead' => $lead->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lead erased.']);

        return back();
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
