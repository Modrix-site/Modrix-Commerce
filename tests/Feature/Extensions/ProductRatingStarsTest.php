<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\BlockPlacements;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\SettingsRepository;
use EcoModular\ProductReviews\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Product Reviews' rating stars under the product title: a block the
 * merchant can switch off or move in the customizer's Product tab.
 */
class ProductRatingStarsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/product-reviews'), $this->modulesPath.'/modrixcommerce/product-reviews');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/product-reviews');
        app(ModuleManager::class)->enable('modrixcommerce/product-reviews', true);
    }

    private function review(int $productId, int $rating, string $status = Review::APPROVED): void
    {
        $review = new Review;
        $review->forceFill(['product_id' => $productId, 'author_name' => 'Ana', 'rating' => $rating, 'body' => 'Lovely product overall.', 'status' => $status, 'verified_purchase' => true])->save();
    }

    public function test_under_the_title_is_a_product_place()
    {
        $this->assertSame('product', StorefrontSlots::PLACES['product.after_title']['tab']);
        $this->assertSame(['product'], StorefrontSlots::PLACES['product.after_title']['context']);
    }

    public function test_stars_show_under_the_title_with_the_approved_average()
    {
        $variant = $this->sellable('20.00', 5);
        $this->review($variant->product_id, 5);
        $this->review($variant->product_id, 4);
        $this->review($variant->product_id, 1, 'pending');

        $this->get(route('storefront.product', $variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('placeSlots', function ($places) {
                $stars = collect($places)->toArray()['product.after_title'][0] ?? null;

                return $stars !== null
                    && $stars['component'] === 'modrixcommerce/product-reviews/RatingStars'
                    && $stars['props']['count'] === 2
                    && $stars['props']['average'] === '4.5';
            }));
    }

    public function test_products_without_reviews_invite_the_first_one()
    {
        $variant = $this->sellable('20.00', 5);

        $this->get(route('storefront.product', $variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('placeSlots', fn ($places) => (collect($places)->toArray()['product.after_title'][0]['props']['count'] ?? null) === 0));
    }

    public function test_the_merchant_can_switch_the_stars_off_or_move_them()
    {
        $variant = $this->sellable('20.00', 5);
        $settings = app(SettingsRepository::class);

        $settings->set(BlockPlacements::KEY, ['modrixcommerce/product-reviews:rating' => ['place' => 'product.after_title', 'order' => 1, 'enabled' => false]]);
        $this->get(route('storefront.product', $variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('placeSlots', fn ($places) => (collect($places)->toArray()['product.after_title'] ?? []) === []));

        $settings->set(BlockPlacements::KEY, ['modrixcommerce/product-reviews:rating' => ['place' => 'product.after_price', 'order' => 1, 'enabled' => true]]);
        $this->get(route('storefront.product', $variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('placeSlots', fn ($places) => (collect($places)->toArray()['product.after_title'] ?? []) === []
                && collect($places)->toArray()['product.after_price'][0]['component'] === 'modrixcommerce/product-reviews/RatingStars'));
    }
}
