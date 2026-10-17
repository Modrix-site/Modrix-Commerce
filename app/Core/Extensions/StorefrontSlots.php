<?php

namespace App\Core\Extensions;

use Closure;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Named places in storefront pages where modules can show a component
 * (stable public API). A module registers a React component from its
 * `resources/js/slots` directory and a closure that builds its props from
 * the page context. Props are plain data serialised to the page.
 *
 * A failing props closure hides that one component; the page still renders.
 */
final class StorefrontSlots
{
    /** Slot name => context keys passed to the props closure. */
    public const SLOTS = [
        'home.after_featured' => [],
        'product.after_details' => ['product'],
        // Admin: a panel in the product editor's sidebar (edit page). Context: the product.
        // Props are built for the signed-in staff member; return null to show nothing.
        'admin.product.sidebar' => ['product'],
        'order.confirmation' => ['order'],
    ];

    /**
     * Places on storefront pages where the merchant may move extension blocks
     * (customizer, per tab). Other slots are fixed: their content stays where
     * the module put it.
     */
    public const PLACES = [
        'header.actions' => ['label' => 'Header icons', 'tab' => 'header', 'context' => []],
        'home.after_featured' => ['label' => 'After featured products', 'tab' => 'home', 'context' => []],
        'catalog.before_products' => ['label' => 'Above the products', 'tab' => 'catalog', 'context' => ['category', 'brand', 'query']],
        'catalog.after_products' => ['label' => 'After the products', 'tab' => 'catalog', 'context' => ['category', 'brand', 'query']],
        // Rendered once per page; each product card adds its own product.
        'product.card' => ['label' => 'On product cards', 'tab' => 'catalog', 'context' => []],
        'product.after_price' => ['label' => 'Under the price', 'tab' => 'product', 'context' => ['product']],
        'product.after_add_to_cart' => ['label' => 'Under add to cart', 'tab' => 'product', 'context' => ['product']],
        'product.after_details' => ['label' => 'After the details', 'tab' => 'product', 'context' => ['product']],
        'product.after_description' => ['label' => 'Below the description', 'tab' => 'product', 'context' => ['product']],
        'cart.after_lines' => ['label' => 'Under the items', 'tab' => 'cart', 'context' => ['cart']],
        'cart.after_totals' => ['label' => 'Under the total', 'tab' => 'cart', 'context' => ['cart']],
        'checkout.after_summary' => ['label' => 'After the summary', 'tab' => 'checkout', 'context' => ['cart']],
        'checkout.before_place_order' => ['label' => 'Above "Place order"', 'tab' => 'checkout', 'context' => ['cart']],
        'storefront.footer' => ['label' => 'Footer', 'tab' => 'footer', 'context' => []],
    ];

    /** @var array<string, list<array{module: string, component: string, props: Closure, order: int}>> fixed slots */
    private array $entries = [];

    /** @var array<string, array{id: string, module: string, key: string, label: string, component: string, places: list<string>, default: string, props: Closure, order: int, seq: int}> */
    private array $blocks = [];

    /**
     * @param  string  $module  the registering module, e.g. "acme/reviews"
     * @param  string  $component  a file name in the module's slots directory, e.g. "ProductReviews"
     * @param  Closure(array<string, mixed>): (array<string, mixed>|null)  $props  return null to show nothing
     */
    public function register(string $slot, string $module, string $component, Closure $props, int $order = 100): void
    {
        if (! array_key_exists($slot, self::SLOTS) && ! array_key_exists($slot, self::PLACES)) {
            throw new InvalidArgumentException("Unknown storefront slot [{$slot}].");
        }

        self::assertComponent($component);

        if (array_key_exists($slot, self::PLACES)) {
            // A one-place block the merchant can still turn off or reorder.
            $this->block($module, "{$component}@{$slot}", $component, $component, [$slot], $slot, $props, $order);

            return;
        }

        $this->entries[$slot][] = ['module' => $module, 'component' => $component, 'props' => $props, 'order' => $order];
    }

    /**
     * Registers a block the merchant may show in any of its places.
     *
     * @param  list<string>  $places  movable places (self::PLACES) the component works in
     * @param  string  $default  where it shows until the merchant moves it
     * @param  Closure(array<string, mixed>): (array<string, mixed>|null)  $props  receives the place's context and "place"
     */
    public function block(string $module, string $key, string $label, string $component, array $places, string $default, Closure $props, int $order = 100): void
    {
        self::assertComponent($component);

        if ($places === [] || array_diff($places, array_keys(self::PLACES)) !== []) {
            throw new InvalidArgumentException("Block [{$module}:{$key}] lists a place that cannot hold blocks.");
        }

        if (! in_array($default, $places, true)) {
            throw new InvalidArgumentException("Block [{$module}:{$key}] defaults to a place it does not list.");
        }

        $id = "{$module}:{$key}";
        $this->blocks[$id] = [
            'id' => $id,
            'module' => $module,
            'key' => $key,
            'label' => $label,
            'component' => $component,
            'places' => array_values(array_unique($places)),
            'default' => $default,
            'props' => $props,
            'order' => $order,
            'seq' => count($this->blocks),
        ];
    }

    /**
     * Registered blocks, for the customizer.
     *
     * @return list<array{id: string, module: string, key: string, label: string, component: string, places: list<string>, default: string}>
     */
    public function blocks(): array
    {
        return array_values(array_map(fn (array $block) => [
            'id' => $block['id'],
            'module' => $block['module'],
            'key' => $block['key'],
            'label' => $block['label'],
            'component' => $block['component'],
            'places' => $block['places'],
            'default' => $block['default'],
        ], $this->blocks));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{key: string, component: string, props: array<string, mixed>}>
     */
    public function render(string $slot, array $context): array
    {
        if (! array_key_exists($slot, self::PLACES)) {
            return $this->renderFixed($slot, $context);
        }

        $placements = app(BlockPlacements::class);
        $here = [];

        foreach ($this->blocks as $block) {
            $saved = $placements->for($block['id']);
            // A saved place the block no longer supports falls back to its default.
            $place = $saved !== null && in_array($saved['place'], $block['places'], true) ? $saved['place'] : $block['default'];

            if ($place === $slot && ($saved['enabled'] ?? true)) {
                $here[] = [$block, $saved['order'] ?? $block['order']];
            }
        }

        usort($here, fn (array $a, array $b) => [$a[1], $a[0]['seq']] <=> [$b[1], $b[0]['seq']]);
        $rendered = [];

        foreach ($here as [$block]) {
            $props = $this->props($slot, $block['module'], $block['props'], [...$context, 'place' => $slot]);

            if ($props !== null) {
                $rendered[] = [
                    'key' => "{$slot}:{$block['id']}",
                    'component' => "{$block['module']}/{$block['component']}",
                    'props' => $props,
                ];
            }
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{key: string, component: string, props: array<string, mixed>}>
     */
    private function renderFixed(string $slot, array $context): array
    {
        $entries = $this->entries[$slot] ?? [];
        usort($entries, fn (array $a, array $b) => $a['order'] <=> $b['order']);
        $rendered = [];

        foreach ($entries as $i => $entry) {
            $props = $this->props($slot, $entry['module'], $entry['props'], $context);

            if ($props !== null) {
                $rendered[] = [
                    'key' => "{$slot}:{$i}",
                    'component' => "{$entry['module']}/{$entry['component']}",
                    'props' => $props,
                ];
            }
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function props(string $slot, string $module, Closure $props, array $context): ?array
    {
        try {
            return $props($context);
        } catch (Throwable $e) {
            Log::error("Storefront slot {$slot} of {$module} failed.", ['exception' => $e]);

            return null;
        }
    }

    private static function assertComponent(string $component): void
    {
        if (! preg_match('/^[A-Z][A-Za-z0-9]{0,60}$/', $component)) {
            throw new InvalidArgumentException("Invalid slot component name [{$component}].");
        }
    }
}
