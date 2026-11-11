<?php

namespace EcoModular\StorePickup\Http\Middleware;

use App\Domains\Cart\Models\Cart;
use Closure;
use EcoModular\StorePickup\StorePickupSettings;
use EcoModular\StorePickup\StorePickupShippingRateProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adjusts checkout payment methods and courier fees when in-store pickup is active.
 * Sets Cash on Delivery as the default payment method.
 * When store pickup is selected, adapts Cash on Delivery to "Pay in store" (waiving courier fee)
 * or hides it based on settings. When delivery is selected, keeps Cash on Delivery.
 */
class StorePickupCheckoutMiddleware
{
    public function __construct(
        private readonly StorePickupSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->settings->enabled()) {
            return $response;
        }

        // Only process storefront checkout requests
        $path = trim($request->path(), '/');
        if ($path !== 'checkout' && ! str_starts_with($path, 'checkout/')) {
            return $response;
        }

        $behavior = $this->settings->codBehavior();
        $isPickupActive = $this->isPickupSelected($request);

        if ($response instanceof InertiaResponse) {
            $this->patchInertiaResponse($response, $isPickupActive, $behavior);

            return $response;
        }

        if ($response instanceof JsonResponse && $response->headers->get('X-Inertia') === 'true') {
            $data = $response->getData(true);
            if (is_array($data) && isset($data['props']) && is_array($data['props'])) {
                $data['props'] = $this->patchProps($data['props'], $isPickupActive, $behavior);
                $response->setData($data);
            }

            return $response;
        }

        $original = $response instanceof \Illuminate\Http\Response ? $response->getOriginalContent() : null;
        if ($original instanceof View) {
            $page = $original->getData()['page'] ?? null;
            if (is_array($page) && isset($page['props']) && is_array($page['props'])) {
                $page['props'] = $this->patchProps($page['props'], $isPickupActive, $behavior);
                $original->with('page', $page);
                $content = $response->getContent();
                if (is_string($content)) {
                    $encoded = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR);
                    $updated = preg_replace_callback(
                        '/<script data-page="app" type="application\/json">.*?<\/script>/s',
                        fn () => '<script data-page="app" type="application/json">'.$encoded.'</script>',
                        $content,
                        1,
                    );
                    if (is_string($updated)) {
                        $response->setContent($updated);
                        $property = new \ReflectionProperty($response, 'original');
                        $property->setValue($response, $original);
                    }
                }
            }
        }

        return $response;
    }

    private function isPickupSelected(Request $request): bool
    {
        $method = $request->input('shipping_method')
            ?: $request->query('shipping_method')
            ?: $request->get('shipping_method');

        if (is_string($method) && $method !== '') {
            return $method === StorePickupShippingRateProvider::METHOD_CODE
                || str_starts_with($method, 'pickup_');
        }

        // Cart shipping method check
        try {
            $cartId = $request->session()->get('cart_id');
            $cart = $cartId ? Cart::query()->find($cartId) : null;
            if ($cart && ! empty($cart->shipping_method_code)) {
                return $cart->shipping_method_code === StorePickupShippingRateProvider::METHOD_CODE
                    || str_starts_with((string) $cart->shipping_method_code, 'pickup_');
            }
        } catch (\Throwable) {
            // ignore
        }

        return false;
    }

    private function patchInertiaResponse(InertiaResponse $response, bool $isPickupActive, string $behavior): void
    {
        $reflection = new \ReflectionProperty($response, 'props');
        $props = $reflection->getValue($response);

        if (! is_array($props)) {
            return;
        }

        if (isset($props['paymentMethods'])) {
            $originalResolver = $props['paymentMethods'];
            $props['paymentMethods'] = function () use ($originalResolver, $isPickupActive, $behavior) {
                $methods = is_callable($originalResolver) ? $originalResolver() : $originalResolver;
                if (! is_array($methods)) {
                    return $methods;
                }

                $methods = $this->reorderPaymentMethods($methods);

                if ($isPickupActive && $behavior !== StorePickupSettings::COD_KEEP) {
                    $methods = $this->transformPaymentMethods($methods, $behavior);
                }

                return $methods;
            };
        }

        if ($isPickupActive && isset($props['summary'])) {
            $originalSummaryResolver = $props['summary'];
            $props['summary'] = function () use ($originalSummaryResolver) {
                $summary = is_callable($originalSummaryResolver) ? $originalSummaryResolver() : $originalSummaryResolver;
                if (! is_array($summary)) {
                    return $summary;
                }

                return $this->transformSummary($summary);
            };
        }

        $reflection->setValue($response, $props);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function patchProps(array $props, bool $isPickupActive, string $behavior): array
    {
        // 1. Payment methods: resolve closure if needed, reorder (COD default), and adapt if pickup active
        if (isset($props['paymentMethods'])) {
            $methods = is_callable($props['paymentMethods'])
                ? ($props['paymentMethods'])()
                : $props['paymentMethods'];

            if (is_array($methods)) {
                $methods = $this->reorderPaymentMethods($methods);

                if ($isPickupActive && $behavior !== StorePickupSettings::COD_KEEP) {
                    $methods = $this->transformPaymentMethods($methods, $behavior);
                }

                $props['paymentMethods'] = $methods;
            }
        }

        // 2. Order summary: resolve closure if needed, zero courier fee if pickup active
        if ($isPickupActive && isset($props['summary'])) {
            $summary = is_callable($props['summary'])
                ? ($props['summary'])()
                : $props['summary'];

            if (is_array($summary)) {
                $props['summary'] = $this->transformSummary($summary);
            }
        }

        return $props;
    }

    /**
     * Prioritizes Cash on Delivery to the first position so it serves as the default payment method.
     *
     * @param  list<array<string, mixed>>  $methods
     * @return list<array<string, mixed>>
     */
    private function reorderPaymentMethods(array $methods): array
    {
        $cod = null;
        $others = [];

        foreach ($methods as $m) {
            if (($m['code'] ?? null) === 'cash_on_delivery') {
                $cod = $m;
            } else {
                $others[] = $m;
            }
        }

        return $cod !== null ? array_merge([$cod], $others) : $methods;
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     * @return list<array<string, mixed>>
     */
    private function transformPaymentMethods(array $methods, string $behavior): array
    {
        if ($behavior === StorePickupSettings::COD_HIDE) {
            return array_values(array_filter($methods, fn ($m) => ($m['code'] ?? null) !== 'cash_on_delivery'));
        }

        if ($behavior === StorePickupSettings::COD_PAY_IN_STORE) {
            return array_values(array_map(function ($m) {
                if (($m['code'] ?? null) === 'cash_on_delivery') {
                    $m['label'] = $this->settings->payInStoreLabel();
                    $m['instructions'] = $this->settings->payInStoreInstructions();
                    $m['fee'] = null;
                }

                return $m;
            }, $methods));
        }

        return $methods;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function transformSummary(array $summary): array
    {
        $currency = $summary['currency'] ?? 'AED';

        if (isset($summary['payment_fee_total'])) {
            $summary['payment_fee_total'] = [
                'amount' => '0.00',
                'currency' => $currency,
                'formatted' => $currency.' 0.00',
            ];
        }

        return $summary;
    }
}
