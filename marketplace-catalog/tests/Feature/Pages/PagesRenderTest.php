<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Marketplace\Models\Category;
use Marketplace\Models\Order;
use Marketplace\Models\Submission;
use Marketplace\Services\Licensing;
use Marketplace\Services\Releases;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Every page opens with data in it. */
class PagesRenderTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_admin_pages_render()
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $package = $this->package(['identifier' => 'acme/labels', 'slug' => 'acme-labels', 'is_free' => false, 'price' => '9.00']);
        Category::query()->create(['slug' => 'marketing', 'name' => 'Marketing']);
        app(Releases::class)->publish($package, $this->packageZip('acme/labels', '1.0.0')->getPathname(), '1.0.0', '^1.0');
        $license = app(Licensing::class)->issue($customer, $package, 'paid');
        Order::query()->create(['user_id' => $customer->id, 'package_id' => $package->id, 'amount' => '9.00', 'currency' => 'USD', 'status' => 'paid', 'license_id' => $license->id]);
        Submission::query()->create(['user_id' => $customer->id, 'identifier' => 'acme/labels', 'kind' => 'update', 'data' => ['version' => '1.1.0', 'changelog' => 'Faster', 'platform_version' => '^1.0']]);

        $this->actingAs($admin);
        foreach (['/admin', '/admin/repository', '/admin/repository/new', '/admin/repository/acme-labels', '/admin/submissions', '/admin/submissions?status=all',
            '/admin/updates', '/admin/categories', '/admin/catalog', '/admin/general', '/admin/general?tab=payments', '/admin/general?tab=email',
            '/admin/general?tab=licensing', '/admin/licenses', '/admin/orders'] as $page) {
            $this->get($page)->assertOk();
        }

        $this->actingAs($customer);
        foreach (['/account', '/account?product=acme-labels', '/submit', '/submit/update', '/submissions', '/item/acme-labels'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_guest_pages_render()
    {
        foreach (['/', '/?type=theme&sort=name&demo=1', '/account/login', '/account/register', '/admin/login', '/admin/setup'] as $page) {
            $this->get($page)->assertOk();
        }
    }
}
