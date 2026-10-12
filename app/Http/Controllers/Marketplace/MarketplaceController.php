<?php

namespace App\Http\Controllers\Marketplace;

use App\Domains\Marketplace\LicenseApi;
use App\Domains\Marketplace\LicenseApiException;
use App\Domains\Marketplace\LicenseManager;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\LicenseActivation;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Domains\Payments\Gateway\GatewayException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Marketplace storefront and customer portal (server role only): browse,
 * buy, see licenses and activations, download entitled releases.
 */
class MarketplaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('storefront/marketplace/index', [
            'products' => MarketplaceProduct::query()->where('status', MarketplaceProduct::PUBLISHED)->orderBy('name')->get()
                ->map(fn (MarketplaceProduct $p) => $this->card($p))->filter()->values(),
        ]);
    }

    public function show(Request $request, MarketplaceProduct $product): Response
    {
        abort_unless($product->status === MarketplaceProduct::PUBLISHED, 404);
        $user = $request->user();

        return Inertia::render('storefront/marketplace/product', [
            'product' => [
                ...(array) $this->card($product),
                'description' => $product->description,
                'documentation_url' => $product->documentation_url,
                'activation_limit' => $product->activation_limit,
                'updates_months' => $product->updates_months,
                'releases' => $product->releases()->where('status', MarketplaceRelease::PUBLISHED)->orderByDesc('published_at')->limit(10)->get()
                    ->map(fn (MarketplaceRelease $r) => [
                        'version' => $r->version,
                        'platform_version' => $r->platform_version,
                        'changelog' => $r->changelog,
                        'published_at' => $r->published_at?->toDateString(),
                        'sha256' => $r->sha256,
                    ]),
            ],
            'owned' => $user instanceof User && License::query()->where('user_id', $user->id)->where('product_id', $product->id)->where('status', License::ACTIVE)->exists(),
        ]);
    }

    public function buy(Request $request, MarketplaceProduct $product, MarketplaceCheckout $checkout): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $checkout->start($user, $product, fn (MarketplaceOrder $order) => [
                route('marketplace.orders.show', $order),
                route('marketplace.product', $product->slug),
            ]);
        } catch (GatewayException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Payments are temporarily unavailable. Please try again later.']);

            return back();
        }

        if ($result['redirect'] === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Your license is ready.']);

            return redirect()->route('marketplace.orders.show', $result['order']);
        }

        return Inertia::location($result['redirect']);
    }

    public function order(Request $request, MarketplaceOrder $order, MarketplaceCheckout $checkout): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($order->user_id === $user->id, 404);

        if ($order->status === MarketplaceOrder::PENDING) {
            try {
                // The redirect from Stripe proves nothing; ask Stripe server-side.
                $order = $checkout->reconcile($order);
            } catch (GatewayException) {
                // The webhook will complete it.
            }
        }

        return Inertia::render('storefront/marketplace/order', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status,
                'total' => $order->money(),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'items' => $order->items()->get()->map(fn ($i) => ['name' => $i->product_name, 'type' => $i->product_type]),
            ],
        ]);
    }

    public function portal(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('storefront/account/marketplace', [
            'licenses' => License::query()->with(['product', 'activations' => fn ($q) => $q->whereNull('deactivated_at')])
                ->where('user_id', $user->id)->latest('id')->get()
                ->map(fn (License $l) => [
                    'id' => $l->id,
                    'product' => $l->product->name,
                    'product_slug' => $l->product->slug,
                    'type' => $l->product->type,
                    'key' => $l->status === License::ACTIVE ? $l->key() : null,
                    'key_hint' => $l->key_hint,
                    'status' => $l->status,
                    'updates_until' => $l->updates_until?->toDateString(),
                    'activation_limit' => $l->activation_limit,
                    'activations' => $l->activations->map(fn (LicenseActivation $a) => [
                        'id' => $a->id,
                        'domain' => $a->domain,
                        'activated_at' => $a->activated_at->toDateString(),
                        'last_seen_at' => $a->last_seen_at?->toDateString(),
                    ])->values(),
                ]),
            'orders' => MarketplaceOrder::query()->where('user_id', $user->id)->latest('id')->limit(50)->get()
                ->map(fn (MarketplaceOrder $o) => ['number' => $o->number, 'status' => $o->status, 'total' => $o->money(), 'created_at' => $o->created_at?->toDateString()]),
        ]);
    }

    public function download(Request $request, License $license, LicenseApi $api): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($license->user_id === $user->id, 404);

        try {
            // A full browser visit (not an Inertia XHR) so the zip downloads.
            return Inertia::location($api->portalDownload($license, $request->ip()));
        } catch (LicenseApiException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    public function deactivate(Request $request, License $license, LicenseActivation $activation, LicenseManager $licenses): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($license->user_id === $user->id && $activation->license_id === $license->id, 404);

        if ($activation->deactivated_at === null) {
            $activation->forceFill(['deactivated_at' => now()])->save();
            $licenses->event($license, 'deactivated', $user, $activation->instance_id, $request->ip(), ['via' => 'portal']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Installation deactivated. You can activate the license on another store.']);

        return back();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function card(MarketplaceProduct $product): ?array
    {
        $release = $product->latestPublishedRelease();

        return $release === null ? null : [
            'slug' => $product->slug,
            'name' => $product->name,
            'summary' => $product->summary,
            'type' => $product->type,
            'price' => $product->money(),
            'free' => $product->isFree(),
            'latest_version' => $release->version,
            'platform_version' => $release->platform_version,
        ];
    }
}
