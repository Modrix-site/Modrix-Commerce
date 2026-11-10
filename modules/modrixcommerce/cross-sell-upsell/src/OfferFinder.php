<?php

namespace EcoModular\CrossSellUpsell;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Pricing\Money;
use EcoModular\CrossSellUpsell\Models\Offer;
use Illuminate\Support\Collection;

/**
 * Finds the offer to show for a product or a cart, and turns its suggested
 * products into storefront cards with the offer price. Only visible,
 * priced, in-stock products that are not already in the cart are suggested.
 */
class OfferFinder
{
    /** @var array<string, Collection<int, Offer>> live offers per place, for this request */
    private array $live = [];

    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly StoreSettings $store,
    ) {}

    /**
     * Live offers shown in a place, best first.
     *
     * @return Collection<int, Offer>
     */
    public function liveIn(string $place): Collection
    {
        $field = Offer::PLACES[$place] ?? null;

        if ($field === null) {
            return new Collection;
        }

        return $this->live[$place] ??= Offer::query()->live()->where($field, true)->orderByDesc('priority')->orderBy('id')->get();
    }

    /**
     * The first offer this product triggers that has something to suggest.
     *
     * @param  list<int>  $exclude  product ids never to suggest (e.g. the cart's)
     * @return array{offer: Offer, items: list<array<string, mixed>>}|null
     */
    public function forProduct(Product $product, string $place, array $exclude, int $limit): ?array
    {
        foreach ($this->liveIn($place) as $offer) {
            if (! $offer->isTriggeredBy($product)) {
                continue;
            }

            $items = $this->suggestions($offer, $product, [...$exclude, $product->id], $limit);

            if ($items !== []) {
                return ['offer' => $offer, 'items' => $items];
            }
        }

        return null;
    }

    /**
     * Suggestions from every offer the cart's products trigger, best offer
     * first, each product once.
     *
     * @param  list<Product>  $products  the cart's products
     * @param  list<int>  $exclude
     * @return list<array<string, mixed>>
     */
    public function forProducts(array $products, string $place, array $exclude, int $limit): array
    {
        $items = [];

        foreach ($this->liveIn($place) as $offer) {
            foreach ($products as $product) {
                if (count($items) >= $limit) {
                    return $items;
                }

                if (! $offer->isTriggeredBy($product)) {
                    continue;
                }

                $taken = [...$exclude, ...array_column($items, 'id')];

                foreach ($this->suggestions($offer, $product, $taken, $limit - count($items)) as $item) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Cards for the offer's products (picked, or found from the trigger's
     * categories), with the offer price.
     *
     * @param  list<int>  $exclude
     * @return list<array<string, mixed>>
     */
    public function suggestions(Offer $offer, Product $trigger, array $exclude, int $limit): array
    {
        $currency = $this->store->currency();

        if ($offer->suggestion_mode === Offer::MANUAL) {
            $ids = array_values(array_diff($offer->ids('product_ids'), $exclude));
        } else {
            $categories = $offer->trigger_type === Offer::TRIGGER_CATEGORIES ? $offer->ids('trigger_ids') : $offer->categoriesOf($trigger);
            $ids = $categories === [] ? [] : array_values(array_map('intval', Product::query()->visible()
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categories))
                ->whereNotIn('id', $exclude)
                ->orderBy('id')
                ->limit(48)
                ->pluck('id')
                ->all()));
        }

        if ($ids === []) {
            return [];
        }

        $cards = array_values(array_filter($this->catalog->cardsFor($ids, $currency, count($ids)), fn (array $card) => $card['in_stock']));

        if ($offer->suggestion_mode === Offer::AUTOMATIC && $offer->type === Offer::UPSELL) {
            // Upgrades: pricier than the product they replace, closest price first.
            $floor = $this->catalog->cardsFor([$trigger->id], $currency, 1)[0]['price'] ?? null;
            $cards = array_values(array_filter($cards, fn (array $card) => $floor instanceof Money && $card['price'] instanceof Money && $card['price']->greaterThan($floor)));
            usort($cards, fn (array $a, array $b) => $a['price']->compareTo($b['price']));
        }

        return array_map(fn (array $card) => $this->withOffer($card, $offer), array_slice($cards, 0, $limit));
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    public function withOffer(array $card, Offer $offer): array
    {
        $price = $card['price'] ?? null;

        return [
            ...$card,
            'offer_id' => $offer->id,
            'offer_price' => $price instanceof Money && $offer->hasDiscount() ? $price->minus($offer->discountFor($price)) : null,
        ];
    }
}
