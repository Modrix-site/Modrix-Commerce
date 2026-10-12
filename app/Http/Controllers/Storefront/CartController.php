<?php

namespace App\Http\Controllers\Storefront;

use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CartManager $carts,
        private readonly CartPricer $pricer,
    ) {}

    public function show(Request $request): Response
    {
        $cart = $this->carts->current($request->session(), $this->user($request));
        $priced = $cart ? $this->pricer->price($cart) : null;

        if ($priced !== null) {
            // Notices are computed above; from now on the current prices count as seen.
            $this->pricer->acknowledgePrices($priced);
        }

        return Inertia::render('storefront/cart', [
            'cart' => $priced?->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartManager::MAX_LINE_QUANTITY],
        ]);

        // Only active variants of visible products can be added; anything else is a 404.
        $variant = ProductVariant::query()
            ->whereKey($data['variant_id'])
            ->whereHas('product', fn ($q) => $q->visible())
            ->firstOrFail();

        $cart = $this->carts->current($request->session(), $this->user($request), create: true);
        abort_if($cart === null, 500);
        $this->carts->add($cart, $variant, (int) $data['quantity']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to your cart.')]);

        return back();
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.CartManager::MAX_LINE_QUANTITY]]);
        $cart = $this->carts->current($request->session(), $this->user($request));
        abort_if($cart === null, 404);

        $this->carts->update($cart, $item, (int) $data['quantity']);

        return redirect()->route('cart.show');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $cart = $this->carts->current($request->session(), $this->user($request));
        abort_if($cart === null, 404);

        $this->carts->remove($cart, $item);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item removed.')]);

        return redirect()->route('cart.show');
    }

    private function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
