<?php

namespace EcoModular\Telr;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The Telr process URL for one payment. The hosted flow redirects straight
 * there. The iframe flow keeps the URL server-side and only embeds it for
 * the customer who placed the order.
 */
final class TelrCheckoutLink
{
    public const HOST = 'secure.telr.com';

    public static function remember(Payment $payment, string $url, ?CarbonInterface $expiresAt): void
    {
        if (! self::isProcessUrl($url)) {
            throw new GatewayException('Telr returned a payment page on an unexpected host.');
        }

        Cache::put(self::key($payment->id), $url, $expiresAt ?? now()->addMinutes(TelrGateway::SESSION_MINUTES));
    }

    /**
     * @throws GatewayException
     */
    public static function urlFor(Payment $payment): string
    {
        $cached = Cache::get(self::key($payment->id));

        if (is_string($cached) && self::isProcessUrl($cached)) {
            return $cached;
        }

        $ref = (string) $payment->provider_reference;

        if (! preg_match('/^[A-Za-z0-9._~-]{6,128}$/', $ref)) {
            throw new GatewayException('This Telr payment page is no longer available.');
        }

        return 'https://'.self::HOST.'/gateway/process.html?o='.rawurlencode($ref);
    }

    public static function isProcessUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $path = (string) ($parts['path'] ?? '');

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === self::HOST
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && str_starts_with($path, '/gateway/');
    }

    private static function key(int $paymentId): string
    {
        return "telr:checkout:{$paymentId}";
    }
}
