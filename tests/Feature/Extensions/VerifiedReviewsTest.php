<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Models\User;
use EcoModular\ProductReviews\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Product Reviews: only verified buyers (a paid order with the product) can
 * review, and only verified-purchase reviews are shown and counted.
 */
class VerifiedReviewsTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpModules();
        File::copyDirectory(base_path('modules/ecomodular/product-reviews'), $this->modulesPath.'/ecomodular/product-reviews');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('ecomodular/product-reviews');
        app(ModuleManager::class)->enable('ecomodular/product-reviews', true);
        app(ModuleManager::class)->configure('ecomodular/product-reviews', ['auto_approve' => '1', 'min_length' => '5']);
        $this->enableCheckout();
        $this->variant = $this->sellable('10.00', 5);
    }

    private function order(User $user, bool $paid): void
    {
        $this->actingAs($user);
        $this->addToCart($this->variant);
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $user->email]))->assertRedirect();
        if ($paid) {
            app(OrderWorkflow::class)->recordManualPayment(Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail(), $this->owner());
        }
    }

    private function review(User $user, string $body = 'Works really well')
    {
        return $this->actingAs($user)->post(route('ecomodular.product-reviews.store', $this->variant->product->slug), ['rating' => 5, 'body' => $body]);
    }

    public function test_customers_who_did_not_buy_cannot_review()
    {
        $customer = User::factory()->create();

        $this->review($customer)->assertSessionHasErrors('body');
        $this->actingAs($customer)->get(route('storefront.product', $this->variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('slots.0.props.reason', 'purchase')
            ->where('slots.0.props.canReview', false));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_an_unpaid_order_is_not_a_verified_purchase()
    {
        $customer = User::factory()->create();
        $this->order($customer, paid: false);

        $this->review($customer)->assertSessionHasErrors('body');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_buyers_review_and_the_review_is_marked_verified()
    {
        $buyer = User::factory()->create();
        $this->order($buyer, paid: true);

        $this->review($buyer)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_reviews', ['user_id' => $buyer->id, 'verified_purchase' => true]);
        $this->get(route('storefront.product', $this->variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('slots.0.props.count', 1)
            ->where('slots.0.props.reviews.0.verified', true));
    }

    public function test_only_verified_reviews_are_shown_and_counted()
    {
        $productId = $this->variant->product_id;
        foreach ([[1, false, 'Ben'], [5, true, 'Ana']] as [$rating, $verified, $author]) {
            $review = new Review;
            $review->forceFill(['product_id' => $productId, 'author_name' => $author, 'rating' => $rating, 'body' => 'Older review text', 'status' => Review::APPROVED, 'verified_purchase' => $verified])->save();
        }

        $this->get(route('storefront.product', $this->variant->product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('slots.0.props.count', 1)
            ->where('slots.0.props.average', '5.0')
            ->has('slots.0.props.reviews', 1)
            ->where('slots.0.props.reviews.0.author', 'Ana')
            ->where('placeSlots', fn ($places) => collect($places)->toArray()['product.after_title'][0]['props']['count'] === 1));
    }

    public function test_purchase_is_always_required_and_no_longer_a_setting()
    {
        $manifest = app(ModuleRepository::class)->find('ecomodular/product-reviews')->manifest();

        $this->assertNotContains('require_purchase', array_column($manifest->settings, 'key'));
    }
}
