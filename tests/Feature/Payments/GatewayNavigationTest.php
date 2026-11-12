<?php

namespace Tests\Feature\Payments;

use App\Core\Admin\AdminNavigation;
use App\Core\Extensions\Manifest;
use App\Core\Extensions\ModuleLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class GatewayNavigationTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->installPlatform();
        $this->setUpModules();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_gateway_capability_places_extension_menu_on_payments_only(): void
    {
        $path = $this->makeHelloModule(overrides: ['capabilities' => ['admin.routes', 'admin.navigation', 'payment.gateway'], 'admin_navigation' => [['key' => 'hello', 'title' => 'Demo gateway', 'route' => 'index', 'icon' => 'credit-card', 'permission' => 'payments.manage']]]);
        $manifest = Manifest::fromArray(json_decode(file_get_contents($path.'/module.json'), true));
        $this->assertTrue(app(ModuleLoader::class)->load('acme/hello', 'acme/hello', $manifest));
        Route::getRoutes()->refreshNameLookups();
        $owner = $this->userWithRole('administrator');
        $nav = app(AdminNavigation::class);
        $this->assertStringNotContainsString('Demo gateway', json_encode($nav->forUser($owner)));
        $this->actingAs($owner)->get(route('admin.payments.settings'))->assertInertia(fn (Assert $page) => $page->where('gatewayExtensions.0.title', 'Demo gateway')->where('gatewayExtensions.0.href', '/admin/m/acme/hello'));
        $this->assertSame([], $nav->paymentGatewaysForUser($this->userWithRole('store-manager')));
    }

    public function test_regular_navigation_and_missing_routes_are_not_injected(): void
    {
        $nav = app(AdminNavigation::class);
        Route::get('/test-regular', fn () => 'ok')->name('test.regular');
        $nav->add('regular', 'Regular extension', 'test.regular', 'puzzle');
        $nav->add('missing', 'Unavailable gateway', 'missing.gateway', 'credit-card', paymentGateway: true);
        Route::getRoutes()->refreshNameLookups();
        $owner = $this->userWithRole('administrator');
        $this->assertStringContainsString('Regular extension', json_encode($nav->forUser($owner)));
        $this->assertSame([], $nav->paymentGatewaysForUser($owner));
        $this->actingAs($owner)->get(route('admin.payments.settings'))->assertInertia(fn (Assert $page) => $page->has('gatewayExtensions', 0));
    }
}
