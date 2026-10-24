<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Marketplace\Models\Category;
use Marketplace\Services\Licensing;
use Marketplace\Services\Releases;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Public catalog: home with filters, package detail, free downloads. */
class CatalogPagesTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_only_published_packages_are_listed()
    {
        $this->package(['name' => 'Visible Reviews']);
        $this->package(['name' => 'Secret Draft', 'status' => 'draft']);
        $this->package(['name' => 'Hidden One', 'status' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('Visible Reviews')->assertDontSee('Secret Draft')->assertDontSee('Hidden One');
    }

    public function test_filters_by_type_price_category_and_search()
    {
        $payments = Category::query()->create(['slug' => 'payments', 'name' => 'Payments', 'type' => 'module']);
        $this->package(['name' => 'Aurora Theme', 'type' => 'theme']);
        $this->package(['name' => 'Paid Gateway', 'is_free' => false, 'price' => '49.00'])->categories()->attach($payments);
        $this->package(['name' => 'Free Labels']);

        $this->get('/?type=theme')->assertSee('Aurora Theme')->assertDontSee('Paid Gateway');
        $this->get('/?tier=premium')->assertSee('Paid Gateway')->assertDontSee('Free Labels');
        $this->get('/?category=payments')->assertSee('Paid Gateway')->assertDontSee('Aurora Theme');
        $this->get('/?q=labels')->assertSee('Free Labels')->assertDontSee('Paid Gateway');
    }

    public function test_detail_page_offers_a_license_or_a_purchase()
    {
        $free = $this->package(['name' => 'Free Labels']);
        $paid = $this->package(['name' => 'Paid Gateway', 'is_free' => false, 'price' => '49.00']);

        $this->get('/item/'.$free->slug)->assertOk()->assertSee('Generate a free license');
        $this->get('/item/'.$paid->slug)->assertOk()->assertSee('49.00')->assertSee('Buy now');
        $this->actingAs($this->customer())->get('/item/'.$paid->slug)->assertSee('action="'.url('/buy/'.$paid->slug).'"', false);
        $this->get('/item/does-not-exist')->assertNotFound();
        $this->get('/item/'.$this->package(['status' => 'draft'])->slug)->assertNotFound();
    }

    public function test_free_packages_download_and_paid_ones_need_a_license()
    {
        $free = $this->package(['identifier' => 'acme/free']);
        $paid = $this->package(['identifier' => 'acme/paid', 'is_free' => false, 'price' => '20.00']);
        app(Releases::class)->publish($free, $this->packageZip('acme/free', '1.0.0')->getPathname(), '1.0.0', '^1.0');
        app(Releases::class)->publish($paid, $this->packageZip('acme/paid', '1.0.0')->getPathname(), '1.0.0', '^1.0');

        $this->get('/download/'.$free->slug)->assertOk()->assertHeader('content-type', 'application/zip');
        $this->get('/download/'.$paid->slug)->assertForbidden();

        $owner = $this->customer();
        app(Licensing::class)->issue($owner, $paid, 'paid');
        $this->actingAs($owner)->get('/download/'.$paid->slug)->assertOk();
    }
}
