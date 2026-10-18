<?php

namespace EcoModular\ProductLabel;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the badges for one product from the merchant's rules: New (published
 * recently), Sale (on offer), Low stock, Featured, Sold out and custom
 * SKU-bound labels. Returns them in priority order; the core caps how many a
 * card shows.
 */
final class LabelResolver
{
    public const MODULE = ProductLabelServiceProvider::MODULE;

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly StoreSettings $store,
    ) {}

    /**
     * @return list<array{key: string, text: string, tone: string, position: string, style: string}>
     */
    public function for(Product $product): array
    {
        if (! (bool) $this->settings->of(self::MODULE, 'enabled', true)) {
            return [];
        }

        $style = $this->style();
        $position = $this->position();
        $currency = $this->store->currency();
        $labels = [];

        if ($this->flag('show_sold_out', true) && ! $this->isInStock($product, $currency)) {
            $labels[] = ['key' => 'sold-out', 'text' => st('Sold out'), 'tone' => 'danger'];
        }

        if ($this->flag('show_sale', true) && $this->isOnSale($product, $currency)) {
            $labels[] = ['key' => 'sale', 'text' => st('Sale'), 'tone' => 'sale'];
        }

        if ($this->flag('show_new', true) && $this->isNew($product)) {
            $labels[] = ['key' => 'new', 'text' => st('New'), 'tone' => 'new'];
        }

        if ($this->flag('show_low_stock', true) && $this->isLowStock($product)) {
            $labels[] = ['key' => 'low-stock', 'text' => st('Low stock'), 'tone' => 'warning'];
        }

        if ($this->flag('show_featured', false) && $product->is_featured) {
            $labels[] = ['key' => 'featured', 'text' => st('Featured'), 'tone' => 'featured'];
        }

        foreach ($this->customLabels($product) as $custom) {
            $labels[] = $custom + ['tone' => 'info'];
        }

        $labels = array_slice($labels, 0, $this->maxLabels());

        return array_map(fn (array $label) => $label + ['position' => $position, 'style' => $style], $labels);
    }

    private function isNew(Product $product): bool
    {
        $published = $product->published_at;

        if (! $published instanceof CarbonInterface) {
            return false;
        }

        $days = max(1, min(365, (int) $this->settings->of(self::MODULE, 'new_days', 21)));

        return $published->greaterThanOrEqualTo(now()->subDays($days));
    }

    private function isOnSale(Product $product, string $currency): bool
    {
        foreach ($this->activeVariants($product) as $variant) {
            $price = $variant->priceIn($currency);
            $compareAt = $variant->compareAtPriceIn($currency);

            if ($price instanceof Money && $compareAt instanceof Money && $compareAt->greaterThan($price)) {
                return true;
            }
        }

        return false;
    }

    private function isInStock(Product $product, string $currency): bool
    {
        foreach ($this->activeVariants($product) as $variant) {
            $price = $variant->priceIn($currency);

            if ($price !== null && ($variant->onHand() === null || $variant->onHand() > 0)) {
                return true;
            }
        }

        return false;
    }

    private function isLowStock(Product $product): bool
    {
        $threshold = max(1, min(100, (int) $this->settings->of(self::MODULE, 'low_stock_threshold', 5)));

        foreach ($this->activeVariants($product) as $variant) {
            $onHand = $variant->onHand();

            if ($onHand !== null && $onHand > 0 && $onHand <= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Custom "SKU = Text" labels that apply to this product.
     *
     * @return list<array{key: string, text: string}>
     */
    private function customLabels(Product $product): array
    {
        $raw = (string) $this->settings->of(self::MODULE, 'custom_labels', '');

        if (trim($raw) === '') {
            return [];
        }

        $skus = $this->activeVariants($product)
            ->map(fn (ProductVariant $v) => strtoupper((string) $v->sku))
            ->all();

        if ($skus === []) {
            return [];
        }

        $labels = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $sku = strtoupper(trim($parts[0]));
            $text = trim($parts[1]);

            if ($sku !== '' && $text !== '' && in_array($sku, $skus, true)) {
                $labels[] = ['key' => 'custom-'.substr(md5($sku), 0, 6), 'text' => $text];
            }
        }

        return $labels;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function activeVariants(Product $product): Collection
    {
        return $product->variants
            ->where('is_active', true)
            ->each(fn (ProductVariant $v) => $v->setRelation('product', $product));
    }

    private function flag(string $key, bool $default): bool
    {
        return (bool) $this->settings->of(self::MODULE, $key, $default);
    }

    private function position(): string
    {
        $position = (string) $this->settings->of(self::MODULE, 'position', 'top-left');

        return in_array($position, ['top-left', 'top-right', 'bottom-left', 'bottom-right'], true) ? $position : 'top-left';
    }

    private function style(): string
    {
        $style = (string) $this->settings->of(self::MODULE, 'badge_style', 'solid');

        return in_array($style, ['solid', 'soft', 'outline'], true) ? $style : 'solid';
    }

    private function maxLabels(): int
    {
        return max(1, min(4, (int) $this->settings->of(self::MODULE, 'max_labels', 2)));
    }
}
