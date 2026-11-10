<?php

namespace EcoModular\Wishlist\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use EcoModular\Wishlist\Models\Wishlist;
use EcoModular\Wishlist\Models\WishlistItem;
use EcoModular\Wishlist\WishlistSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin controller for wishlist analytics & settings.
 */
class WishlistAdminController extends Controller
{
    public function index(WishlistSettings $settings, StoreSettings $store): Response
    {
        $currency = $store->currency();

        $totalWishlists = Wishlist::query()->count();
        $customerWishlists = Wishlist::query()->whereNotNull('user_id')->count();
        $totalItems = WishlistItem::query()->count();

        // Top wishlisted products
        $topProductsQuery = WishlistItem::query()
            ->select('product_id', DB::raw('count(*) as saves_count'))
            ->groupBy('product_id')
            ->orderByDesc('saves_count')
            ->limit(10)
            ->get();

        $productIds = $topProductsQuery->pluck('product_id')->all();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with(['variants.prices', 'images'])
            ->get()
            ->keyBy('id');

        $topProducts = [];
        foreach ($topProductsQuery as $row) {
            $product = $products->get($row->product_id);
            if ($product === null) {
                continue;
            }

            $variant = $product->variants->first();
            $price = $variant?->priceIn($currency) ?? Money::zero($currency);

            $topProducts[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $variant?->sku ?? '-',
                'image' => $product->images->first()?->url(),
                'saves_count' => (int) $row->saves_count,
                'price_formatted' => $price->format(),
            ];
        }

        return Inertia::render('module/modrixcommerce/wishlist/admin/index', [
            'stats' => [
                'total_wishlists' => $totalWishlists,
                'customer_wishlists' => $customerWishlists,
                'guest_wishlists' => $totalWishlists - $customerWishlists,
                'total_saved_items' => $totalItems,
            ],
            'top_products' => $topProducts,
            'settings' => $settings->toArray(),
            'urls' => [
                'settings' => route('admin.modrixcommerce.wishlist.settings'),
            ],
        ]);
    }

    public function settings(WishlistSettings $settings): Response
    {
        return Inertia::render('module/modrixcommerce/wishlist/admin/settings', [
            'settings' => $settings->toArray(),
            'updateUrl' => route('admin.modrixcommerce.wishlist.settings.update'),
        ]);
    }

    public function updateSettings(
        Request $request,
        WishlistSettings $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'require_login' => ['boolean'],
            'allow_sharing' => ['boolean'],
            'button_label' => ['required', 'string', 'max:60'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['require_login'] = $request->boolean('require_login');
        $data['allow_sharing'] = $request->boolean('allow_sharing');

        $settings->save($data);

        $audit->record('wishlist.settings_updated', null, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Wishlist settings saved successfully.'),
        ]);

        return redirect()->route('admin.modrixcommerce.wishlist.settings');
    }
}
