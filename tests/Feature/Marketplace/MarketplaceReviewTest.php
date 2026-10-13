<?php

namespace Tests\Feature\Marketplace;

use App\Domains\Marketplace\LicenseManager;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Phase 8.5: reviews by license holders, moderated before they appear.
 */
class MarketplaceReviewTest extends TestCase
{
    use MarketplaceFixtures, ModuleFixtures, RefreshDatabase;

    private MarketplaceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->setUpMarketplace();
        $this->product = $this->product(['price' => '0', 'support_email' => 'help@acme.test']);
        $this->publish($this->product, $this->packageModule());
    }

    private function holder(): User
    {
        $user = User::factory()->create();
        app(MarketplaceCheckout::class)->start($user, $this->product, fn () => ['https://x.test/a', 'https://x.test/b']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function review(array $overrides = []): array
    {
        return array_merge(['rating' => 5, 'title' => 'Great', 'body' => 'Works exactly as described, setup took a minute.'], $overrides);
    }

    public function test_only_license_holders_review_once_and_reviews_wait_for_moderation()
    {
        $route = route('marketplace.reviews.store', $this->product);

        $this->actingAs(User::factory()->create())->post($route, $this->review())->assertSessionHasErrors('body');

        $holder = $this->holder();
        $this->actingAs($holder)->get(route('marketplace.product', $this->product))
            ->assertInertia(fn (Assert $page) => $page->where('reviews.canReview', true)->where('product.support_email', 'help@acme.test'));
        $this->actingAs($holder)->post($route, $this->review(['rating' => 9]))->assertSessionHasErrors('rating');
        $this->actingAs($holder)->post($route, $this->review())->assertSessionHasNoErrors();
        $this->actingAs($holder)->post($route, $this->review())->assertSessionHasErrors('body');

        // Pending: not public yet.
        $this->get(route('marketplace.product', $this->product))->assertInertia(fn (Assert $page) => $page->has('reviews.items', 0)->where('reviews.count', 0));
        $this->actingAs($holder)->get(route('marketplace.product', $this->product))
            ->assertInertia(fn (Assert $page) => $page->where('reviews.mine', 'pending')->where('reviews.canReview', false));

        // The owner approves it.
        $review = MarketplaceReview::query()->firstOrFail();
        $this->actingAs($holder)->post(route('admin.marketplace.reviews.moderate', $review), ['decision' => 'approve'])->assertForbidden();
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('admin.marketplace.reviews.index'))->assertInertia(fn (Assert $page) => $page->has('reviews', 1));
        $this->actingAs($owner)->post(route('admin.marketplace.reviews.moderate', $review), ['decision' => 'approve'])->assertRedirect();

        $this->get(route('marketplace.product', $this->product))
            ->assertInertia(fn (Assert $page) => $page->where('reviews.count', 1)->where('reviews.average', 5)->where('reviews.items.0.title', 'Great'));
    }

    public function test_revoked_or_refunded_licenses_cannot_review()
    {
        $holder = $this->holder();
        app(LicenseManager::class)->changeStatus(License::query()->where('user_id', $holder->id)->firstOrFail(), License::REVOKED, 'Abuse', $this->owner());

        $this->actingAs($holder)->post(route('marketplace.reviews.store', $this->product), $this->review())->assertSessionHasErrors('body');
        $this->assertDatabaseCount('marketplace_reviews', 0);
    }
}
