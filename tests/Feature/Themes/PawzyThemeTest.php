<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeException;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\ThemeDemoImporter;
use App\Domains\Content\Models\Page;
use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class PawzyThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
        File::copyDirectory(base_path('themes/modrixcommerce/pawzy'), $this->themesPath.'/modrixcommerce/pawzy');
        $this->withoutVite();
        $this->enableCheckout();
        Storage::fake(config('ecomodular.media_disk'));
        Mail::fake();
    }

    private function pawzy(): Extension
    {
        app(ThemeRepository::class)->sync();
        $theme = Extension::query()->where('name', 'modrixcommerce/pawzy')->firstOrFail();
        $this->assertNotSame(ModuleState::Invalid, $theme->state, implode(' ', (array) $theme->errors));

        return $theme;
    }

    public function test_package_owns_editor_and_all_declared_entries_and_assets_resolve(): void
    {
        $theme = $this->pawzy();
        $this->actingAs($this->owner())->get(route('admin.themes.customize', $theme))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('customizer.component', 'src/customizer.tsx')
            ->where('design.title', 'Pawzy theme studio')
            ->where('design.defaults', fn ($defaults) => $defaults['custom.show_sidebar'] === true));
        app(ThemeManager::class)->activate($theme->name);
        $this->get('/')->assertOk()->assertInertia(function (Assert $page) {
            $page->where('storefront.theme.name', 'modrixcommerce/pawzy')
                ->where('storefront.theme.frontend.product', 'src/product.tsx')
                ->where('storefront.theme.frontend.header', 'src/header.tsx');
            foreach ($page->toArray()['props']['sections'] as $section) {
                if ($section['data']['image'] ?? null) {
                    $this->get($section['data']['image'])->assertOk();
                }
                foreach ($section['data']['tiles'] ?? [] as $tile) {
                    if ($tile['image']) {
                        $this->get($tile['image'])->assertOk();
                    }
                }
            }
        });
    }

    public function test_theme_settings_validate_and_survive_rendering(): void
    {
        $theme = $this->pawzy();
        $owner = $this->owner();
        $this->actingAs($owner)->put(route('admin.themes.design', $theme), ['design' => [
            'custom.show_sidebar' => false, 'custom.hero_height' => 520, 'custom.hero_title_size' => 64, 'custom.hero_show' => false,
            'font_body' => 'rounded', 'style.general.body_size' => '16',
        ]])->assertSessionHasNoErrors();
        app(ThemeManager::class)->activate($theme->name);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.settings.show_sidebar', false)
            ->where('storefront.theme.settings.hero_height', 520)
            ->where('storefront.theme.settings.hero_title_size', 64)
            ->where('storefront.theme.settings.hero_show', false)
            ->where('storefront.theme.settings.hero_heading', 'Smiles, Wags, And Love'));
        $this->put(route('admin.themes.design', $theme), ['design' => ['custom.hero_height' => 99999]])->assertSessionHasErrors();
    }

    public function test_demo_is_additive_imports_gallery_and_pages_and_checkout_uses_core_totals(): void
    {
        $theme = $this->pawzy();
        $owner = $this->owner();
        $manifest = app(ThemeManager::class)->renderedManifest($theme);
        $existing = Page::create(['title' => 'My return policy', 'slug' => 'pawzy-returns', 'content' => 'Keep this policy.', 'status' => 'published']);
        $deleted = Page::create(['title' => 'Old page', 'slug' => 'pawzy-privacy', 'content' => 'Keep deleted.', 'status' => 'draft']);
        $deleted->delete();
        $first = app(ThemeDemoImporter::class)->import($manifest, $owner);
        $second = app(ThemeDemoImporter::class)->import($manifest, $owner);
        $this->assertSame(['categories' => 9, 'products' => 15, 'pages' => 4], $first);
        $this->assertSame(['categories' => 0, 'products' => 0, 'pages' => 0], $second);
        $this->assertSame('Keep this policy.', $existing->fresh()->content);
        $this->assertSoftDeleted($deleted);
        $product = Product::query()->where('slug', 'pawzy-heart-pattern-pet-bed')->firstOrFail();
        $this->assertSame(3, $product->images()->count());
        app(ThemeManager::class)->activate($theme->name);
        $this->get('/pages/pawzy-our-story')->assertOk();
        $this->get('/c/pawzy-cats')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data'));
        $this->get(route('storefront.product', $product->slug))->assertOk();
        $this->addToCart($product->variants()->firstOrFail(), 2);
        $this->get(route('cart.show'))->assertOk();
        $this->get(route('checkout.show'))->assertOk();
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame('33.0000', $order->grand_total);
        $this->get(route('checkout.complete', $order))->assertOk();
    }

    public function test_demo_rejects_gallery_paths_outside_assets_before_writing_and_cleans_page_html(): void
    {
        $theme = $this->pawzy();
        $manifest = app(ThemeManager::class)->renderedManifest($theme);
        $file = $this->themesPath.'/modrixcommerce/pawzy/demo.json';
        $data = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
        $data['products'][0]['images'] = ['../../../../.env'];
        File::put($file, json_encode($data, JSON_THROW_ON_ERROR));
        $owner = $this->owner();
        try {
            app(ThemeDemoImporter::class)->import($manifest, $owner);
            $this->fail('An invalid gallery must reject the whole import.');
        } catch (ThemeException $error) {
            $this->assertStringContainsString('gallery', $error->getMessage());
        }
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Page::query()->where('slug', 'like', 'pawzy-%')->count());
        $data['products'][0]['images'] = [];
        $data['pages'][0]['content'] = '<p>Our pets</p><script>alert(1)</script><img src=x onerror=alert(1)>';
        File::put($file, json_encode($data, JSON_THROW_ON_ERROR));
        app(ThemeDemoImporter::class)->import($manifest, $owner);
        $content = Page::query()->where('slug', 'pawzy-our-story')->firstOrFail()->content;
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
    }
}
