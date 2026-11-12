<?php

namespace App\Core\Catalog;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Where modules badge storefront product cards. A module with the
 * "product.labels" capability registers a closure that returns label arrays
 * for a product; the core merges them into the product-card data as "labels"
 * and every theme renders them (ADR: modules declare, the core renders).
 *
 * Each label is a small, presentation-ready array:
 *   ['key' => string, 'text' => string, 'tone' => string, 'position' => string, 'style' => string]
 * where tone is one of new|sale|featured|stock|info|warning|danger, position is
 * one of top-left|top-right|bottom-left|bottom-right and style is one of
 * solid|soft|outline.
 *
 * A provider that throws is skipped and logged, so a broken module cannot take
 * the product cards down.
 */
final class ProductLabels
{
    public const TONES = ['new', 'sale', 'featured', 'stock', 'info', 'warning', 'danger'];

    public const POSITIONS = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];

    public const STYLES = ['solid', 'soft', 'outline'];

    /** A card never shows more than this many labels; providers are trimmed in order. */
    public const MAX_PER_CARD = 4;

    /** @var array<string, Closure(mixed): iterable<array<string, mixed>>> */
    private array $providers = [];

    /**
     * @param  Closure(mixed): iterable<array<string, mixed>>  $provider  receives the product model
     */
    public function register(string $module, Closure $provider): void
    {
        $this->providers[$module] = $provider;
    }

    public function isActive(): bool
    {
        return $this->providers !== [];
    }

    /**
     * Sanitised labels for one product, in provider order, capped per card.
     *
     * @return list<array{key: string, text: string, tone: string, position: string, style: string}>
     */
    public function labels(mixed $product): array
    {
        $labels = [];
        $seen = [];

        foreach ($this->providers as $module => $provider) {
            try {
                $items = $provider($product);
            } catch (Throwable $e) {
                Log::error("Product label provider {$module} failed.", ['exception' => $e]);

                continue;
            }

            foreach ($items as $item) {
                $label = $this->normalize($item);

                if ($label === null || isset($seen[$label['key']])) {
                    continue;
                }

                $seen[$label['key']] = true;
                $labels[] = $label;

                if (count($labels) >= self::MAX_PER_CARD) {
                    return $labels;
                }
            }
        }

        return $labels;
    }

    /**
     * @return array{key: string, text: string, tone: string, position: string, style: string}|null
     */
    private function normalize(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $text = isset($item['text']) ? trim((string) $item['text']) : '';

        if ($text === '') {
            return null;
        }

        $tone = isset($item['tone']) ? (string) $item['tone'] : 'info';
        $position = isset($item['position']) ? (string) $item['position'] : 'top-left';
        $style = isset($item['style']) ? (string) $item['style'] : 'solid';
        $key = isset($item['key']) ? (string) $item['key'] : $text;

        return [
            'key' => substr(preg_replace('/[^a-z0-9_-]/i', '', $key) ?? $text, 0, 40) ?: 'label',
            'text' => mb_substr($text, 0, 40),
            'tone' => in_array($tone, self::TONES, true) ? $tone : 'info',
            'position' => in_array($position, self::POSITIONS, true) ? $position : 'top-left',
            'style' => in_array($style, self::STYLES, true) ? $style : 'solid',
        ];
    }
}
