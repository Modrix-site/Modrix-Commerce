<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Marketplace\Models\Payout;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Marketplace\Models\SellerApplication;
use App\Domains\Marketplace\SellerAccounts;
use App\Domains\Marketplace\SellerLedger;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller administration for the platform owner (marketplace server):
 * applications, commission, suspension and payouts.
 */
class MarketplaceSellerController extends Controller
{
    public function index(SellerLedger $ledger): Response
    {
        return Inertia::render('admin/marketplace/sellers', [
            'applications' => SellerApplication::query()->with('user:id,email')->where('status', SellerApplication::PENDING)->oldest()->get()
                ->map(fn (SellerApplication $a) => [
                    'id' => $a->id,
                    'publisher_name' => $a->publisher_name,
                    'vendor_slug' => $a->vendor_slug,
                    'legal_name' => $a->legal_name,
                    'country' => $a->country,
                    'website' => $a->website,
                    'payout_email' => $a->payout_email,
                    'support_email' => $a->support_email,
                    'description' => $a->description,
                    'email' => $a->user->email,
                    'created_at' => $a->created_at?->toDateString(),
                ]),
            'publishers' => Publisher::query()->where('is_platform', false)->withCount('products')->orderBy('name')->get()
                ->map(fn (Publisher $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'status' => $p->status,
                    'commission_percent' => $p->commission_percent,
                    'products_count' => (int) $p->getAttribute('products_count'),
                    'payout_email' => $p->payout_email,
                    'balances' => collect($ledger->totals($p))->map(fn (array $t) => $t['net'])->values(),
                    'payouts' => Payout::query()->where('publisher_id', $p->id)->latest('paid_at')->limit(5)->get()
                        ->map(fn (Payout $o) => ['id' => $o->id, 'amount' => $o->money(), 'reference' => $o->reference, 'paid_at' => $o->paid_at->toDateString()]),
                ]),
        ]);
    }

    public function approve(Request $request, SellerApplication $application, SellerAccounts $sellers): RedirectResponse
    {
        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $publisher = $sellers->approve($application, number_format((float) $data['commission_percent'], 2, '.', ''), $data['notes'] ?? null, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$publisher->name} can now publish under {$publisher->slug}/."]);

        return back();
    }

    public function reject(Request $request, SellerApplication $application, SellerAccounts $sellers): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);
        $sellers->reject($application, $data['notes'], $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application rejected.']);

        return back();
    }

    public function update(Request $request, Publisher $publisher, SellerAccounts $sellers): RedirectResponse
    {
        abort_if($publisher->is_platform, 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['commission', 'suspend', 'reactivate'])],
            'commission_percent' => ['required_if:action,commission', 'nullable', 'numeric', 'min:0', 'max:100'],
            'reason' => ['required_if:action,suspend', 'nullable', 'string', 'max:500'],
        ]);
        $actor = $this->actor($request);

        match ($data['action']) {
            'commission' => $sellers->setCommission($publisher, number_format((float) $data['commission_percent'], 2, '.', ''), $actor),
            'suspend' => $sellers->setStatus($publisher, Publisher::SUSPENDED, (string) $data['reason'], $actor),
            'reactivate' => $sellers->setStatus($publisher, Publisher::ACTIVE, 'Reactivated', $actor),
            default => abort(422),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Seller updated.']);

        return back();
    }

    public function payout(Request $request, Publisher $publisher, SellerLedger $ledger): RedirectResponse
    {
        abort_if($publisher->is_platform, 404);
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'currency' => ['required', 'string', 'size:3'],
            'reference' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $ledger->recordPayout($publisher, Money::of($data['amount'], strtoupper($data['currency'])), $data['reference'], $data['notes'] ?? null, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payout recorded.']);

        return back();
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
