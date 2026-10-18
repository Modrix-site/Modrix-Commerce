<?php

namespace EcoModular\CheckoutCom;

use App\Domains\Payments\Models\Payment;
use Illuminate\Support\Facades\Cache;

/**
 * The Flow payment session is kept server-side and shown only to the customer
 * who placed the order. It contains the session secret Flow needs in the browser.
 */
final class CheckoutComFlowSession
{
    /**
     * @param  array<string, mixed>  $session
     */
    public static function remember(Payment $payment, array $session): void
    {
        Cache::put(self::key($payment->id), $session, now()->addHours(CheckoutComGateway::SESSION_HOURS));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(Payment $payment): ?array
    {
        $session = Cache::get(self::key($payment->id));

        return is_array($session) ? $session : null;
    }

    private static function key(int $paymentId): string
    {
        return "checkout-com:flow:{$paymentId}";
    }
}
