<?php

namespace Tests\Feature\Marketplace;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Admin → Modrix Cloud catalog cards: an installed state that matches
 * Admin → Extensions, and thumbnails.
 *
 * Admin → Modrix Cloud shows the catalog thumbnails Modrix Cloud serves from
 * its own host (/media/<slug>). The store's Content-Security-Policy must allow
 * that host, or the browser blocks every image and the cards show broken images.
 */
class CloudCatalogTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private const CLOUD = 'https://cloud-apps.modrixcommerce.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false, 'ecomodular.marketplace.url' => '']);
        $this->installPlatform();
        $this->setUpModules();
        $this->enableBundledModule('modrixcommerce/cloud-connector');

        Http::fake([
            self::CLOUD.'/api/marketplace/v1/catalog' => Http::response(['products' => [
                $this->row('modrixcommerce/paypal', 'PayPal', self::CLOUD.'/media/modrixcommerce-paypal'),
                $this->row('modrixcommerce/advanced-seo', 'Advanced SEO', null),
                $this->row('acme/sneaky', 'Sneaky', 'javascript:alert(1)'),
                $this->row('acme/hello', 'Hello', null),
            ]]),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(string $identifier, string $name, ?string $thumbnail): array
    {
        return [
            'identifier' => $identifier, 'type' => 'module', 'name' => $name, 'slug' => str_replace('/', '-', $identifier),
            'summary' => '', 'price' => '0.00', 'currency' => 'USD', 'is_free' => true, 'featured' => false,
            'thumbnail_url' => $thumbnail, 'demo_url' => null, 'url' => self::CLOUD.'/item/x', 'latest_version' => '1.0.0',
        ];
    }

    public function test_the_catalog_passes_the_cloud_thumbnail_urls_to_the_page()
    {
        $this->actingAs($this->owner())->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/cloud-connector/index', false)
                ->where('catalog.0.thumbnail_url', self::CLOUD.'/media/modrixcommerce-paypal')
                // No thumbnail: the page shows the puzzle placeholder.
                ->where('catalog.1.thumbnail_url', null)
                // Anything but http(s) is dropped.
                ->where('catalog.2.thumbnail_url', null));
    }

    public function test_the_content_security_policy_allows_images_from_modrix_cloud()
    {
        $csp = (string) $this->actingAs($this->owner())->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('#(^|;)\s*img-src [^;]*'.preg_quote(self::CLOUD, '#').'(\s|;|$)#', $csp);
        // Only images: the cloud host gets no script or frame access.
        $this->assertDoesNotMatchRegularExpression('#script-src [^;]*'.preg_quote(self::CLOUD, '#').'#', $csp);
    }

    /** @return array<string, mixed> */
    private function helloCard(): array
    {
        $catalog = $this->actingAs($this->owner())->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->assertOk()->viewData('page')['props']['catalog'];

        return collect($catalog)->firstWhere('identifier', 'acme/hello');
    }

    public function test_a_package_only_discovered_on_disk_is_not_shown_as_installed()
    {
        // Bundled files, as every first-party module ships in modules/: discovered, not installed.
        $this->makeHelloModule();

        $card = $this->helloCard();
        $this->assertNull($card['installed_version']);
        $this->assertTrue($card['on_disk']);
        $this->assertSame('discovered', $card['state']);
        $this->assertSame('Not installed', $card['state_label']);
        $this->assertStringEndsWith('/admin/extensions/'.Extension::query()->where('name', 'acme/hello')->value('id'), $card['manage_url']);

        // Admin → Extensions agrees.
        $this->get(route('admin.extensions.index'))->assertInertia(fn (Assert $page) => $page
            ->where('extensions', fn ($rows) => collect($rows)->firstWhere('name', 'acme/hello')['state'] === 'discovered'));
    }

    public function test_the_installed_badge_follows_the_core_lifecycle()
    {
        $this->makeHelloModule();
        $this->helloCard();
        $modules = app(ModuleManager::class);

        $modules->install('acme/hello');
        $card = $this->helloCard();
        $this->assertSame('1.0.0', $card['installed_version']);
        $this->assertSame('installed', $card['state']);

        $modules->enable('acme/hello', true);
        $this->assertSame('enabled', $this->helloCard()['state']);

        $modules->disable('acme/hello');
        $modules->uninstall('acme/hello', 'acme/hello');
        $card = $this->helloCard();
        $this->assertNull($card['installed_version']);
        $this->assertSame('discovered', $card['state']);
    }

    public function test_a_package_not_in_this_store_has_no_state()
    {
        $card = collect($this->actingAs($this->owner())->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->viewData('page')['props']['catalog'])->firstWhere('identifier', 'modrixcommerce/paypal');

        $this->assertFalse($card['on_disk']);
        $this->assertNull($card['installed_version']);
        $this->assertNull($card['state']);
    }
}
