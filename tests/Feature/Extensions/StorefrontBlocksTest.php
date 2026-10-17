<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\BlockPlacements;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class StorefrontBlocksTest extends TestCase
{
    use RefreshDatabase;

    private StorefrontSlots $slots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->slots = new StorefrontSlots;
    }

    private function reviews(int $order = 100): void
    {
        $this->slots->block('acme/reviews', 'reviews', 'Product reviews', 'Reviews', ['product.after_details', 'product.after_description'], 'product.after_details', fn (array $context) => ['place' => $context['place']], $order);
    }

    private function place(array $placements): void
    {
        app(SettingsRepository::class)->set(BlockPlacements::KEY, $placements);
    }

    /** @return list<string> */
    private function components(string $place): array
    {
        return array_column($this->slots->render($place, ['product' => null]), 'component');
    }

    public function test_block_renders_at_its_default_place()
    {
        $this->reviews();

        $rendered = $this->slots->render('product.after_details', ['product' => null]);
        $this->assertSame(['acme/reviews/Reviews'], array_column($rendered, 'component'));
        $this->assertSame('product.after_details', $rendered[0]['props']['place']);
        $this->assertSame([], $this->components('product.after_description'));
    }

    public function test_moved_block_renders_only_at_its_new_place()
    {
        $this->reviews();
        $this->place(['acme/reviews:reviews' => ['place' => 'product.after_description', 'order' => 10, 'enabled' => true]]);

        $this->assertSame([], $this->components('product.after_details'));
        $this->assertSame(['acme/reviews/Reviews'], $this->components('product.after_description'));
    }

    public function test_disabled_block_does_not_render()
    {
        $this->reviews();
        $this->place(['acme/reviews:reviews' => ['place' => 'product.after_details', 'order' => 10, 'enabled' => false]]);

        $this->assertSame([], $this->components('product.after_details'));
    }

    public function test_saved_order_wins()
    {
        $this->reviews(10);
        $this->slots->block('acme/promo', 'promo', 'Promo', 'Promo', ['product.after_details'], 'product.after_details', fn () => [], 20);
        $this->assertSame(['acme/reviews/Reviews', 'acme/promo/Promo'], $this->components('product.after_details'));

        $this->place(['acme/promo:promo' => ['place' => 'product.after_details', 'order' => 1, 'enabled' => true]]);
        $this->assertSame(['acme/promo/Promo', 'acme/reviews/Reviews'], $this->components('product.after_details'));
    }

    public function test_unsupported_saved_place_falls_back()
    {
        $this->reviews();
        $this->place(['acme/reviews:reviews' => ['place' => 'cart.after_lines', 'order' => 10, 'enabled' => true]]);

        $this->assertSame(['acme/reviews/Reviews'], $this->components('product.after_details'));
        $this->assertSame([], array_column($this->slots->render('cart.after_lines', ['cart' => null]), 'component'));
    }

    public function test_unknown_block_entries_are_ignored()
    {
        $this->place(['gone/module:block' => ['place' => 'product.after_details', 'order' => 1, 'enabled' => true]]);
        $this->reviews();

        $this->assertSame(['acme/reviews/Reviews'], $this->components('product.after_details'));
        $this->assertArrayHasKey('gone/module:block', app(BlockPlacements::class)->all());
    }

    public function test_block_rejects_unknown_or_fixed_places()
    {
        foreach ([
            [['product.nowhere'], 'product.nowhere'],
            [['storefront.overlay'], 'storefront.overlay'],
            [['product.after_details'], 'product.after_price'],
        ] as [$places, $default]) {
            try {
                $this->slots->block('acme/x', 'x', 'X', 'X', $places, $default, fn () => []);
                $this->fail('Expected an exception for '.implode(',', $places));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_register_still_works_and_is_listed_as_a_block()
    {
        $this->slots->register('cart.after_totals', 'acme/pay', 'PayPromo', fn () => ['ok' => true]);
        $this->slots->register('product.after_details', 'acme/pay', 'PayPromo', fn () => ['ok' => true]);
        $this->slots->register('storefront.overlay', 'acme/pop', 'Popup', fn () => []);

        $this->assertSame(['acme/pay/PayPromo'], array_column($this->slots->render('cart.after_totals', ['cart' => null]), 'component'));
        $this->assertSame(['acme/pop/Popup'], array_column($this->slots->render('storefront.overlay', []), 'component'));

        $blocks = collect($this->slots->blocks())->keyBy('id');
        $this->assertSame(['cart.after_totals'], $blocks['acme/pay:PayPromo@cart.after_totals']['places']);
        $this->assertSame('product.after_details', $blocks['acme/pay:PayPromo@product.after_details']['default']);
        // Fixed places are not offered as movable blocks.
        $this->assertFalse($blocks->has('acme/pop:Popup'));
    }

    public function test_failing_block_hides_alone()
    {
        $this->slots->block('acme/broken', 'broken', 'Broken', 'Broken', ['product.after_details'], 'product.after_details', fn () => throw new RuntimeException('boom'), 1);
        $this->reviews();

        $this->assertSame(['acme/reviews/Reviews'], $this->components('product.after_details'));
    }
}
