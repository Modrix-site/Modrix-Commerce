<?php

namespace EcoModular\Tabby\Http;

use App\Domains\Cart\CartManager;
use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Models\User;
use EcoModular\Tabby\TabbyApi;
use EcoModular\Tabby\TabbyCopy;
use EcoModular\Tabby\TabbyPayload;
use EcoModular\Tabby\TabbySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Background pre-scoring: may this shopper use Tabby for this amount?
 *
 * Advisory only. It never creates an order or payment, and the checkout
 * session is checked again when the order is placed. Following Tabby's
 * guidance it fails open: any error or timeout answers "eligible".
 */
class TabbyEligibilityController extends Controller
{
    private const CACHE_MINUTES = 10;

    public function __invoke(Request $request, TabbySettings $settings, TabbyApi $api, TabbyPayload $payload, CartManager $carts): JsonResponse
    {
        // Only for shoppers with a cart: not an open lookup of anyone's Tabby eligibility.
        $user = $request->user();
        $cart = $carts->current($request->session(), $user instanceof User ? $user : null);
        abort_if($cart === null || ! $cart->items()->exists(), 404);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9\s()-]{6,40}$/'],
            'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ]);

        $market = $settings->market();

        if (! $settings->ready() || $market === null || ! $settings->option('eligibility_check', true)) {
            return response()->json(['eligible' => true]);
        }

        $amount = Money::of($data['amount'], $market->currency);
        $key = 'tabby-eligibility:'.hash('sha256', implode('|', [$settings->merchantCode(), $amount->toDisplayDecimal(), strtolower($data['email']), $data['phone']]));

        $reason = Cache::get($key);

        if (! is_string($reason)) {
            try {
                $response = $api->request('post', '/api/v2/checkout', $payload->eligibility($amount, $data['email'], $data['phone']), timeout: 8);
                $reason = ($response['status'] ?? null) === 'rejected'
                    ? (string) ($response['configuration']['products']['installments']['rejection_reason'] ?? 'not_available')
                    : '';
                Cache::put($key, $reason, now()->addMinutes(self::CACHE_MINUTES));
            } catch (GatewayException) {
                return response()->json(['eligible' => true]);
            }
        }

        return response()->json($reason === ''
            ? ['eligible' => true]
            : ['eligible' => false, 'message' => TabbyCopy::rejection($reason)]);
    }
}
