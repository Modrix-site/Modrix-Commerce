<?php

namespace EcoModular\CrossSellUpsell\Http;

use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\VariantAvailability;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\CrossSellUpsell\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Storefront: adds an offer's product to the cart (remembering the offer for
 * its price and stats) and takes a checkout add-on out again. The request
 * names the offer and product; both are re-checked here, never trusted.
 */
class OfferController extends Controller
{
    public function add(Request $request, CartManager $carts, StoreSettings $store): RedirectResponse
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'variant_id' => ['required', 'integer'],
            'place' => ['required', Rule::in(array_keys(Offer::PLACES))],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartManager::MAX_LINE_QUANTITY],
        ]);

        $offer = Offer::query()->live()->find((int) $data['offer_id']);
        abort_unless($offer instanceof Offer && $offer->showsIn($data['place']), 404);

        $variant = ProductVariant::query()
            ->whereKey((int) $data['variant_id'])
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->visible())
            ->with(['product.categories:id', 'prices', 'stocks'])
            ->first();
        abort_unless($variant instanceof ProductVariant && $offer->maySuggest($variant->product), 404);

        $quantity = (int) ($data['quantity'] ?? 1);
        $availability = VariantAvailability::of($variant, $store->currency());

        if (! $availability->allows($quantity)) {
            throw ValidationException::withMessages(['offer' => $availability->message() ?? st('This product is unavailable.')]);
        }

        $cart = $carts->current($request->session(), $this->user($request), create: true);
        abort_if($cart === null, 500);
        $carts->add($cart, $variant, $quantity);

        DB::table('cross_sell_cart_lines')->updateOrInsert(
            ['cart_id' => $cart->id, 'variant_id' => $variant->id],
            ['offer_id' => $offer->id, 'created_at' => now(), 'updated_at' => now()],
        );
        $offer->increment('adds');

        Inertia::flash('toast', ['type' => 'success', 'message' => st('Added to your cart.')]);

        return back();
    }

    /** Unticking a checkout add-on: only lines added from that offer. */
    public function remove(Request $request, CartManager $carts): RedirectResponse
    {
        $data = $request->validate([
            'offer_id' => ['required', 'integer'],
            'variant_id' => ['required', 'integer'],
        ]);

        $cart = $carts->current($request->session(), $this->user($request));
        abort_if($cart === null, 404);

        $linked = DB::table('cross_sell_cart_lines')
            ->where('cart_id', $cart->id)
            ->where('variant_id', (int) $data['variant_id'])
            ->where('offer_id', (int) $data['offer_id'])
            ->exists();
        abort_unless($linked, 404);

        $item = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', (int) $data['variant_id'])->first();

        if ($item instanceof CartItem) {
            $carts->remove($cart, $item);
        }

        DB::table('cross_sell_cart_lines')->where('cart_id', $cart->id)->where('variant_id', (int) $data['variant_id'])->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => st('Item removed.')]);

        return back();
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
