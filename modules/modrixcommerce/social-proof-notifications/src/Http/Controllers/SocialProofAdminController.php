<?php

namespace EcoModular\SocialProofNotifications\Http\Controllers;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use EcoModular\SocialProofNotifications\Models\SocialProofEvent;
use EcoModular\SocialProofNotifications\Services\SocialProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class SocialProofAdminController extends Controller
{
    public function __construct(
        protected SocialProofService $socialProofService
    ) {}

    public function index(Request $request): Response
    {
        $query = SocialProofEvent::query()->latest('occurred_at');

        if ($request->filled('type')) {
            $query->where('event_type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'simulated') {
                $query->where('is_simulated', true);
            } elseif ($status === 'real') {
                $query->where('is_simulated', false);
            }
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('customer_city', 'like', $search);
            });
        }

        /** @var LengthAwarePaginator<int, SocialProofEvent> $paginated */
        $paginated = $query->paginate(20)->withQueryString();

        $events = $paginated->through(fn (SocialProofEvent $e) => [
            'id' => $e->id,
            'eventType' => $e->event_type,
            'productName' => $e->product_name,
            'productId' => $e->product_id,
            'productImageUrl' => $e->product_image_url,
            'productUrl' => $e->product_url,
            'customerName' => $e->customer_name,
            'maskedName' => $e->maskedCustomerName(),
            'customerCity' => $e->customer_city,
            'customerCountry' => $e->customer_country,
            'location' => $e->formattedLocation(),
            'message' => $e->message,
            'timeAgo' => $e->timeAgo(),
            'occurredAt' => $e->occurred_at?->toIso8601String(),
            'isActive' => $e->is_active,
            'isSimulated' => $e->is_simulated,
            'impressionsCount' => $e->impressions_count,
            'clicksCount' => $e->clicks_count,
            'ctr' => $e->impressions_count > 0 ? round(($e->clicks_count / $e->impressions_count) * 100, 1) : 0,
        ]);

        $availableProducts = Product::query()
            ->where('status', ProductStatus::Published)
            ->withCurrentTranslation()
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->localized('name'),
                'slug' => $p->slug,
            ]);

        return Inertia::render('module/modrixcommerce/social-proof-notifications/admin/index', [
            'metrics' => $this->socialProofService->getAdminMetrics(),
            'events' => $events,
            'filters' => [
                'type' => $request->query('type', ''),
                'status' => $request->query('status', ''),
                'search' => $request->query('search', ''),
            ],
            'config' => $this->socialProofService->config(),
            'availableProducts' => $availableProducts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', 'in:recent_purchase,added_to_cart,product_viewers,low_stock,recent_review,custom_alert'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['required', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_city' => ['nullable', 'string', 'max:120'],
            'customer_country' => ['nullable', 'string', 'max:10'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $productId = ! empty($validated['product_id']) ? (int) $validated['product_id'] : null;
        $product = $productId !== null ? Product::query()->with('images')->find($productId) : null;
        $imageUrl = ($product instanceof Product) ? $product->images->first()?->url() : null;
        $productUrl = ($product instanceof Product) ? "/p/{$product->slug}" : null;

        SocialProofEvent::query()->create([
            'event_type' => $validated['event_type'],
            'product_id' => $validated['product_id'] ?? null,
            'product_name' => $validated['product_name'],
            'product_image_url' => $imageUrl,
            'product_url' => $productUrl,
            'customer_name' => $validated['customer_name'] ?? 'Verified Buyer',
            'customer_city' => $validated['customer_city'] ?? null,
            'customer_country' => $validated['customer_country'] ?? null,
            'message' => $validated['message'] ?? null,
            'occurred_at' => now(),
            'is_active' => true,
            'is_simulated' => false,
        ]);

        return back()->with('success', 'Social proof event created successfully.');
    }

    public function toggle(SocialProofEvent $event): RedirectResponse
    {
        $event->update(['is_active' => ! $event->is_active]);

        $status = $event->is_active ? 'activated' : 'paused';

        return back()->with('success', "Notification event {$status}.");
    }

    public function destroy(SocialProofEvent $event): RedirectResponse
    {
        $event->delete();

        return back()->with('success', 'Notification event deleted.');
    }

    public function seedSample(): RedirectResponse
    {
        $count = $this->socialProofService->seedSampleEvents();

        return back()->with('success', "Generated {$count} sample social proof events.");
    }

    public function clearAll(): RedirectResponse
    {
        SocialProofEvent::query()->delete();

        return back()->with('success', 'All social proof events cleared.');
    }
}
