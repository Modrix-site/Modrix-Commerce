<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Http\Controllers\Controller;
use App\Support\Like;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders → Customer carts: what shoppers (signed in or guests) have in
 * their carts right now, most recently active first (`can:orders.view`).
 * Read-only; empty carts are left out.
 */
class CustomerCartController extends Controller
{
    public function index(Request $request, CartPricer $pricer): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($filters['search'] ?? ''));

        $carts = Cart::query()
            ->whereHas('items')
            ->with('user:id,name,email')
            ->withSum('items as quantity', 'quantity')
            ->when($search !== '', fn ($query) => $query->whereHas('user', function ($user) use ($search) {
                $user->where(function ($u) use ($search) {
                    Like::where($u, 'email', $search);
                    Like::orWhere($u, 'name', $search);
                });
            }))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Cart $cart) use ($pricer) {
                $priced = $pricer->price($cart);
                $names = array_map(fn ($line) => $line->variant->product->localized('name'), $priced->lines);

                return [
                    'id' => $cart->id,
                    'customer' => $cart->user ? ['name' => $cart->user->name, 'email' => $cart->user->email] : null,
                    'items_count' => (int) $cart->getAttribute('quantity'),
                    'products' => array_slice($names, 0, 2),
                    'more_products' => max(0, count($names) - 2),
                    'subtotal' => $priced->subtotal(),
                    'coupon_code' => $cart->coupon_code,
                    'updated_at' => $cart->updated_at?->toIso8601String(),
                ];
            });

        return Inertia::render('admin/carts/index', [
            'carts' => $carts,
            'filters' => ['search' => $search],
        ]);
    }
}
