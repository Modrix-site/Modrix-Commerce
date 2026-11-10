<?php

namespace EcoModular\BuyTogetherSave;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricedLine;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Pricing\Money;
use Brick\Math\RoundingMode;
use EcoModular\BuyTogetherSave\Models\BuyTogetherDeal;
use Illuminate\Support\Facades\DB;

/**
 * Applies bundle discounts to cart lines added through "Buy Together & Save" deals.
 */
class BuyTogetherPricingStep implements PricingStep
{
    public function __construct(
        private readonly BuyTogetherSettings $settings,
    ) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if (! $cart->exists || empty($priced->lines)) {
            return;
        }

        if (! $this->settings->enabled()) {
            return;
        }

        $rows = DB::table('buy_together_cart_lines')
            ->where('cart_id', $cart->id)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        // Map cart variants by ID
        /** @var array<int, PricedLine> $linesByVariantId */
        $linesByVariantId = [];
        foreach ($priced->lines as $line) {
            $linesByVariantId[$line->variant->id] = $line;
        }

        // Clean up rows for variants that left the cart
        $orphanedIds = $rows->filter(fn ($row) => ! isset($linesByVariantId[(int) $row->variant_id]))
            ->pluck('id')
            ->all();

        if (! empty($orphanedIds)) {
            DB::table('buy_together_cart_lines')->whereIn('id', $orphanedIds)->delete();
            $rows = $rows->whereNotIn('id', $orphanedIds);
        }

        // Each bundle added discounts one unit of each of its items. A line
        // shared by several bundles needs a unit per bundle; extra quantity
        // the shopper adds is charged in full.
        /** @var array<int, int> $unitsUsed */
        $unitsUsed = [];

        foreach ($rows->sortBy('id')->groupBy('bundle_group_id') as $groupRows) {
            $dealId = $groupRows->first()->deal_id ?? null;

            if ($dealId !== null) {
                // A deal that is switched off, expired or deleted stops discounting.
                $deal = BuyTogetherDeal::query()->live()->find($dealId);
                if ($deal === null) {
                    continue;
                }
                $discountType = $deal->discount_type;
                $discountValue = (float) $deal->discount_value;
                $minimum = max(2, (int) $deal->min_items_required);
            } else {
                // The automatic same-category bundle uses the store defaults.
                if (! $this->settings->autoBundleFallback()) {
                    continue;
                }
                $discountType = $this->settings->defaultDiscountType();
                $discountValue = $this->settings->defaultDiscountValue();
                $minimum = 2;
            }

            /** @var list<PricedLine> $groupLines */
            $groupLines = [];

            foreach ($groupRows->unique('variant_id') as $row) {
                $line = $linesByVariantId[(int) $row->variant_id] ?? null;
                if ($line instanceof PricedLine && $line->isPurchasable() && $line->unitPrice !== null
                    && $line->quantity > ($unitsUsed[$line->variant->id] ?? 0)) {
                    $groupLines[] = $line;
                }
            }

            if (count($groupLines) < $minimum) {
                continue;
            }

            $setTotal = Money::zero($priced->currency);
            foreach ($groupLines as $line) {
                $unitsUsed[$line->variant->id] = ($unitsUsed[$line->variant->id] ?? 0) + 1;
                $setTotal = $setTotal->plus($line->unitPrice);
            }

            if (! $setTotal->isPositive() || $discountValue <= 0) {
                continue;
            }

            if ($discountType === 'percent') {
                $percent = number_format(min(100.0, $discountValue), 4, '.', '');
                $label = 'Bundle: save '.rtrim(rtrim($percent, '0'), '.').'%';

                foreach ($groupLines as $line) {
                    $line->applyOfferDiscount($priced, $line->unitPrice->percentage($percent)->rounded(), $label);
                }

                continue;
            }

            // Fixed amount off the set, split by each item's share of it; the
            // last item takes the rounding remainder.
            $fixed = Money::of(number_format($discountValue, 2, '.', ''), $priced->currency)->min($setTotal);
            $remaining = $fixed;
            $last = count($groupLines) - 1;

            foreach ($groupLines as $index => $line) {
                $portion = $index === $last
                    ? $remaining
                    : $fixed->multipliedBy($line->unitPrice->amount()->dividedBy($setTotal->amount(), 10, RoundingMode::HALF_UP))->rounded();
                $remaining = $remaining->minus($portion);
                $line->applyOfferDiscount($priced, $portion, 'Bundle savings');
            }
        }
    }
}
