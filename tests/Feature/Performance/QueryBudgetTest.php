<?php

namespace Tests\Feature\Performance;

use App\Domains\Catalog\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * Lazy-loading prevention catches N+1 through relations; this catches the
 * rest (queries issued in loops): the number of queries for list pages must
 * not grow with the number of rows shown.
 */
class QueryBudgetTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Mail::fake();
    }

    private function queries(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function assertFlat(int $small, int $large, string $page): void
    {
        $this->assertLessThanOrEqual($small + 2, $large, "{$page}: {$small} queries for a few rows, {$large} for many.");
    }

    public function test_storefront_lists_do_not_query_per_product()
    {
        $category = Category::factory()->create(['slug' => 'all']);
        $add = function (int $n) use ($category) {
            for ($i = 0; $i < $n; $i++) {
                $category->products()->attach($this->sellable(product: ['name' => "Widget {$i}".uniqid()])->product_id);
            }
        };

        $add(2);
        $small = ['/' => $this->queries('/'), 'category' => $this->queries('/c/all'), 'search' => $this->queries('/search?q=Widget')];
        $add(14);
        $this->assertFlat($small['/'], $this->queries('/'), 'home');
        $this->assertFlat($small['category'], $this->queries('/c/all'), 'category');
        $this->assertFlat($small['search'], $this->queries('/search?q=Widget'), 'search');
    }

    public function test_cart_does_not_query_per_line()
    {
        $this->addToCart($this->sellable());
        $small = $this->queries('/cart');

        for ($i = 0; $i < 8; $i++) {
            $this->addToCart($this->sellable());
        }

        $this->assertFlat($small, $this->queries('/cart'), 'cart');
    }

    public function test_order_lists_do_not_query_per_order()
    {
        $this->enableCheckout();
        $customer = User::factory()->create();
        $owner = $this->owner();
        $place = function (int $n) use ($customer) {
            for ($i = 0; $i < $n; $i++) {
                $this->actingAs($customer);
                $this->addToCart($this->sellable());
                $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $customer->email]))->assertRedirect();
            }
        };

        $place(2);
        $this->actingAs($owner);
        $adminSmall = $this->queries('/admin/orders');
        $productsSmall = $this->queries('/admin/products');
        $this->actingAs($customer);
        $accountSmall = $this->queries('/account/orders');

        $place(8);
        $this->actingAs($owner);
        $this->assertFlat($adminSmall, $this->queries('/admin/orders'), 'admin orders');
        $this->assertFlat($productsSmall, $this->queries('/admin/products'), 'admin products');
        $this->actingAs($customer);
        $this->assertFlat($accountSmall, $this->queries('/account/orders'), 'account orders');
    }
}
