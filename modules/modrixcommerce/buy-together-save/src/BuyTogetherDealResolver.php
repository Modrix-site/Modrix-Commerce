<?php

namespace EcoModular\BuyTogetherSave;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Money;
use EcoModular\BuyTogetherSave\Models\BuyTogetherDeal;
use Illuminate\Support\Collection;

/**
 * Resolves active bundle deals for a given product and prepares widget props.
 */
class BuyTogetherDealResolver
{
    public function __construct(
        private readonly BuyTogetherSettings $settings,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolveForProduct(Product $product): ?array
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $offer = $this->offer($product);

        if ($offer === null) {
            return null;
        }

        /** @var BuyTogetherDeal|null $matchedDeal */
        $matchedDeal = $offer['deal'];
        $companionProducts = $offer['companions'];
        $dealId = $matchedDeal?->id;
        $discountType = $matchedDeal ? $matchedDeal->discount_type : $this->settings->defaultDiscountType();
        $discountValue = $matchedDeal ? (float) $matchedDeal->discount_value : $this->settings->defaultDiscountValue();
        $badgeText = $matchedDeal
            ? $matchedDeal->badgeLabel()
            : ($discountType === 'percent' ? 'Save '.intval($discountValue).'%' : 'Bundle Savings');

        if ($companionProducts->isEmpty()) {
            return null;
        }

        $currency = $this->store->currency();

        // Build main item
        $mainVariant = $this->defaultVariant($product);
        if ($mainVariant === null) {
            return null;
        }

        $mainPrice = $mainVariant->priceIn($currency) ?? Money::zero($currency);

        $mainItem = [
            'id' => $product->id,
            'name' => $product->localized('name'),
            'slug' => $product->slug,
            'image' => $this->imageUrl($product),
            'variant_id' => $mainVariant->id,
            'price' => (float) $mainPrice->toDisplayDecimal(),
            'formatted_price' => $mainPrice->format(),
            'is_main' => true,
        ];

        // Build companion items
        $items = [];
        $totalOriginal = $mainPrice;

        foreach ($companionProducts as $companion) {
            $variant = $this->defaultVariant($companion);
            if ($variant === null) {
                continue;
            }

            $price = $variant->priceIn($currency) ?? Money::zero($currency);
            $totalOriginal = $totalOriginal->plus($price);

            $items[] = [
                'id' => $companion->id,
                'name' => $companion->localized('name'),
                'slug' => $companion->slug,
                'image' => $this->imageUrl($companion),
                'variant_id' => $variant->id,
                'price' => (float) $price->toDisplayDecimal(),
                'formatted_price' => $price->format(),
                'is_main' => false,
            ];
        }

        if ($items === []) {
            return null;
        }

        // Calculate bundle savings
        $savings = Money::zero($currency);
        if ($discountType === 'percent') {
            $savings = $totalOriginal->percentage((string) $discountValue)->rounded();
        } else {
            $savings = Money::of(number_format(min((float) $totalOriginal->toDisplayDecimal(), $discountValue), 2, '.', ''), $currency);
        }

        $totalBundle = $totalOriginal->minus($savings)->nonNegative();

        return [
            'deal_id' => $dealId,
            'heading' => $this->settings->heading(),
            'subheading' => $this->settings->subheading(),
            'badge_text' => $badgeText,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'cta_button_text' => $this->settings->ctaButtonText(),
            'show_savings_badge' => $this->settings->showSavingsBadge(),
            'currency' => $currency,
            'main_item' => $mainItem,
            'items' => $items,
            'total_original' => (float) $totalOriginal->toDisplayDecimal(),
            'total_original_formatted' => $totalOriginal->format(),
            'total_bundle' => (float) $totalBundle->toDisplayDecimal(),
            'total_bundle_formatted' => $totalBundle->format(),
            'savings_amount' => (float) $savings->toDisplayDecimal(),
            'savings_amount_formatted' => $savings->format(),
            'add_bundle_url' => route('modrixcommerce.buy-together-save.add'),
        ];
    }

    /**
     * What the product page offers for this product: the live deal that
     * targets it (null for the automatic same-category bundle) and the
     * companion products. The add-to-cart endpoint checks requests against
     * this, so shoppers can only bundle what the page offered.
     *
     * @return array{deal: BuyTogetherDeal|null, companions: Collection<int, Product>}|null
     */
    public function offer(Product $product): ?array
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $deal = BuyTogetherDeal::query()->live()->get()
            ->first(fn (BuyTogetherDeal $deal) => $deal->appliesToProduct($product));
        $companions = collect();

        if ($deal !== null) {
            $companionIds = array_map('intval', (array) ($deal->bundle_product_ids ?? []));
            if ($companionIds !== []) {
                $companions = Product::query()
                    ->visible()
                    ->whereIn('id', $companionIds)
                    ->where('id', '!=', $product->id)
                    ->with(['variants.prices', 'images', 'categories'])
                    ->get();
            }
        }

        // Automatic same-category companions when no deal offers any.
        if ($companions->isEmpty() && $this->settings->autoBundleFallback()) {
            $deal = null;
            $categoryIds = $product->categories->pluck('id')->all();
            if ($product->category_id !== null) {
                $categoryIds[] = $product->category_id;
            }

            $query = Product::query()
                ->visible()
                ->where('id', '!=', $product->id)
                ->with(['variants.prices', 'images'])
                ->orderBy('id');

            if ($categoryIds !== []) {
                $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
            }

            $companions = $query->limit(2)->get();
        }

        return $companions->isEmpty() ? null : ['deal' => $deal, 'companions' => $companions];
    }

    private function defaultVariant(Product $product): ?ProductVariant
    {
        return $product->variants
            ->where('is_active', true)
            ->first();
    }

    private function imageUrl(Product $product): ?string
    {
        return $product->images->first()?->url();
    }
}
