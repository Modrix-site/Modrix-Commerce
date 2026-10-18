<?php

namespace EcoModular\CrossSellUpsell;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricedLine;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use EcoModular\CrossSellUpsell\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * Gives lines added from an offer the offer's price (runs before coupons).
 *
 * A line keeps the discount only while its offer is live and still suggests
 * the product, and a product that triggers the offer (and was not itself
 * added from an offer) is in the cart. At most as many units are discounted
 * as there are trigger units.
 */
class OfferPricing implements PricingStep
{
    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if (! $cart->exists) {
            return;
        }

        $rows = DB::table('cross_sell_cart_lines')->where('cart_id', $cart->id)->get(['variant_id', 'offer_id']);

        if ($rows->isEmpty()) {
            return;
        }

        $lines = [];
        foreach ($priced->lines as $line) {
            $lines[$line->variant->id] = $line;
        }

        // Lines that left the cart lose their link to the offer.
        $gone = $rows->filter(fn (object $row) => ! isset($lines[(int) $row->variant_id]))->pluck('variant_id')->all();
        if ($gone !== []) {
            DB::table('cross_sell_cart_lines')->where('cart_id', $cart->id)->whereIn('variant_id', $gone)->delete();
        }

        $fromOffers = $rows->pluck('offer_id', 'variant_id')->map(fn ($id) => (int) $id)->all();
        $offers = Offer::query()->live()->whereIn('id', array_unique($fromOffers))->get()->keyBy('id');

        foreach ($fromOffers as $variantId => $offerId) {
            $line = $lines[(int) $variantId] ?? null;
            $offer = $offers->get($offerId);

            if (! $line instanceof PricedLine || ! $offer instanceof Offer || ! $offer->hasDiscount() || ! $line->isPurchasable() || $line->unitPrice === null) {
                continue;
            }

            if (! $offer->maySuggest($line->variant->product)) {
                continue;
            }

            $units = min($line->quantity, $this->triggerUnits($offer, $line, $priced, $fromOffers));

            if ($units > 0) {
                $amount = $offer->discountFor($line->unitPrice)->multipliedBy($units)->rounded();
                $line->applyOfferDiscount($priced, $amount, $offer->label());
            }
        }
    }

    /**
     * Units in the cart of products that trigger the offer, other than the
     * line itself and lines that were added from offers.
     *
     * @param  array<int, int>  $fromOffers  variant id => offer id
     */
    private function triggerUnits(Offer $offer, PricedLine $line, PricedCart $priced, array $fromOffers): int
    {
        $units = 0;

        foreach ($priced->purchasableLines() as $other) {
            $product = $other->variant->product;

            if ($other === $line || isset($fromOffers[$other->variant->id]) || $product->id === $line->variant->product_id) {
                continue;
            }

            if ($offer->isTriggeredBy($product)) {
                $units += $other->quantity;
            }
        }

        return $units;
    }
}
