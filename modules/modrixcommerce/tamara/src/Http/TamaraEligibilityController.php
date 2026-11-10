<?php

namespace EcoModular\Tamara\Http;

use App\Domains\Cart\CartManager;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\Tamara\TamaraCopy;
use EcoModular\Tamara\TamaraSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Background availability check for the Tamara method at checkout.
 *
 * Advisory only: it never creates an order or payment, and the checkout
 * session (and Tamara itself) is checked again when the order is placed. It
 * answers from the merchant's own configuration (market and order limits) so
 * it never invents a Tamara decision; it fails open on any error.
 */
class TamaraEligibilityController extends Controller
{
    public function __invoke(Request $request, TamaraSettings $settings, CartManager $carts): JsonResponse
    {
        // Only for shoppers with a cart: not an open probe of the store's limits.
        $user = $request->user();
        $cart = $carts->current($request->session(), $user instanceof User ? $user : null);
        abort_if($cart === null || ! $cart->items()->exists(), 404);

        $data = $request->validate([
            'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ]);

        $market = $settings->market();

        if (! $settings->ready() || $market === null || ! $settings->option('eligibility_check', true)) {
            return response()->json(['eligible' => true]);
        }

        $amount = Money::of($data['amount'], $market->currency);

        if ($settings->allowsAmount($amount)) {
            return response()->json(['eligible' => true]);
        }

        $min = (int) $settings->option('min_amount', 0);
        $max = (int) $settings->option('max_amount', 0);
        $reason = $max > 0 && $amount->greaterThan(Money::of($max, $market->currency))
            ? 'order_amount_too_high'
            : ($min > 0 ? 'order_amount_too_low' : 'not_available');

        return response()->json(['eligible' => false, 'message' => TamaraCopy::rejection($reason)]);
    }
}
