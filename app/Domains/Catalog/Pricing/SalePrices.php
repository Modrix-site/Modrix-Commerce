<?php

namespace App\Domains\Catalog\Pricing;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Money;
use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sale prices from every registered SalePriceProvider. When several offers
 * cover a product, the one giving the lowest price wins. Offers are looked
 * up once per product per request; call preload() for a page of products.
 *
 * A failing provider is logged and ignored, so a broken module can never
 * stop the catalog or checkout from working (it only loses its discount).
 */
#[Singleton]
final class SalePrices
{
    /** @var list<SalePriceProvider> */
    private array $providers = [];

    /** @var list<Closure(ProductVariant, Money): Money> */
    private array $variantAdjusters = [];

    /** @var array<int, list<SaleOffer>> product id => offers */
    private array $offers = [];

    /** Cached offers belong to one request, and are never older than MAX_AGE seconds. */
    private const MAX_AGE = 30;

    private ?int $requestId = null;

    private ?int $loadedAt = null;

    public function addProvider(SalePriceProvider $provider): void
    {
        $this->providers[] = $provider;
        $this->offers = [];
    }

    /**
     * Register a callback that adjusts a variant's storefront price (e.g. selected product addons).
     *
     * @param  Closure(ProductVariant, Money): Money  $adjuster
     */
    public function addVariantAdjuster(Closure $adjuster): void
    {
        $this->variantAdjusters[] = $adjuster;
    }

    public function adjustVariantPrice(ProductVariant $variant, ?Money $price): ?Money
    {
        if ($price === null || $this->variantAdjusters === []) {
            return $price;
        }

        foreach ($this->variantAdjusters as $adjuster) {
            try {
                $price = $adjuster($variant, $price);
            } catch (Throwable $e) {
                Log::warning('A variant price adjuster failed; its adjustment is ignored.', ['error' => $e->getMessage()]);
            }
        }

        return $price;
    }

    public function hasProviders(): bool
    {
        return $this->providers !== [];
    }

    /**
     * Looks up offers for these products in one go.
     *
     * @param  iterable<int>  $productIds
     */
    public function preload(iterable $productIds): void
    {
        // Queue workers, console commands and Octane keep this singleton (and
        // can reuse the request object), so the cache also expires with time.
        $request = app()->bound('request') ? spl_object_id(app('request')) : null;
        if ($request !== $this->requestId || $this->loadedAt === null || time() - $this->loadedAt > self::MAX_AGE) {
            $this->offers = [];
            $this->requestId = $request;
            $this->loadedAt = time();
        }

        $missing = [];
        foreach ($productIds as $id) {
            if (! array_key_exists((int) $id, $this->offers)) {
                $missing[(int) $id] = (int) $id;
            }
        }

        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->offers[$id] = [];
        }

        $now = now();
        foreach ($this->providers as $provider) {
            try {
                foreach ($provider->offers(array_values($missing), $now) as $productId => $offers) {
                    if (isset($missing[$productId])) {
                        foreach ($offers instanceof SaleOffer ? [$offers] : $offers as $offer) {
                            $this->offers[$productId][] = $offer;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('A sale price provider failed; its offers are ignored.', ['provider' => $provider::class, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * The best offer for a product price, if any.
     *
     * @return array{price: Money, original: Money, offer: SaleOffer}|null
     */
    public function apply(int $productId, Money $price): ?array
    {
        if ($this->providers === []) {
            return null;
        }

        $this->preload([$productId]);
        $best = null;
        $now = now();

        foreach ($this->offers[$productId] as $offer) {
            // An offer that ended since it was looked up no longer applies.
            if (! $offer->appliesTo($price->currency()) || ($offer->endsAt !== null && $offer->endsAt->lessThanOrEqualTo($now))) {
                continue;
            }

            $reduced = $offer->apply($price);

            if ($reduced->lessThan($price) && ($best === null || $reduced->lessThan($best['price']))) {
                $best = ['price' => $reduced, 'original' => $price, 'offer' => $offer];
            }
        }

        return $best;
    }

    /**
     * @return array{price: Money, original: Money, offer: SaleOffer}|null
     */
    public function forVariant(ProductVariant $variant, string $currency): ?array
    {
        $price = $variant->priceIn($currency);

        return $price === null ? null : $this->apply($variant->product_id, $price);
    }

    /** Clears cached offers (between requests in long-running workers and in tests). */
    public function flush(): void
    {
        $this->offers = [];
        $this->loadedAt = null;
    }
}
