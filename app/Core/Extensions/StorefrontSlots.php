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

    /** @var array<string, list<array{module: string, component: string, props: Closure, order: int}>> */
    private array $entries = [];

    /**
     * @param  string  $module  the registering module, e.g. "acme/reviews"
     * @param  string  $component  a file name in the module's slots directory, e.g. "ProductReviews"
     * @param  Closure(array<string, mixed>): (array<string, mixed>|null)  $props  return null to show nothing
     */
    public function register(string $slot, string $module, string $component, Closure $props, int $order = 100): void
    {
        if (! array_key_exists($slot, self::SLOTS)) {
            throw new InvalidArgumentException("Unknown storefront slot [{$slot}].");
        }

        if (! preg_match('/^[A-Z][A-Za-z0-9]{0,60}$/', $component)) {
            throw new InvalidArgumentException("Invalid slot component name [{$component}].");
        }

        $this->entries[$slot][] = ['module' => $module, 'component' => $component, 'props' => $props, 'order' => $order];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{key: string, component: string, props: array<string, mixed>}>
     */
    public function render(string $slot, array $context): array
    {
        $entries = $this->entries[$slot] ?? [];
        usort($entries, fn (array $a, array $b) => $a['order'] <=> $b['order']);
        $rendered = [];

        foreach ($entries as $i => $entry) {
            try {
                $props = ($entry['props'])($context);
            } catch (Throwable $e) {
                Log::error("Storefront slot {$slot} of {$entry['module']} failed.", ['exception' => $e]);

                continue;
            }

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
}
