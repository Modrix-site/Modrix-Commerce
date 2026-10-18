<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeManifest;
use App\Domains\Catalog\ThemeDemoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class HearthThemeTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        $this->setUpThemes();
        config(['inertia.ssr.enabled' => false]);
    }

    private function upload(): Extension
    {
        $file = new UploadedFile(base_path('dist/ecomodular-hearth-1.0.0.zip'), 'hearth.zip', 'application/zip', null, true);
        $this->actingAs($this->userWithRole('administrator'))->post(route('admin.themes.packages.store'), ['package' => $file])->assertSessionHasNoErrors();

        return Extension::where('name', 'ecomodular/hearth')->firstOrFail();
    }

    public function test_zip_installs_with_its_own_frontend_and_settings_without_activation(): void
    {
        $theme = $this->upload();
        $this->assertSame(ModuleState::Discovered, $theme->state);
        $this->assertNull($theme->applied_manifest);
        $this->assertFileExists($this->themesPath.'/ecomodular/hearth/settings.json');
        $this->assertFileExists($this->themesPath.'/ecomodular/hearth/src/product.tsx');
        $this->assertCount(110, $theme->manifest['customizer']['fields']);
        $this->assertSame('src/header.tsx', $theme->manifest['frontend']['header']);
        $this->assertSame('src/footer.tsx', $theme->manifest['frontend']['footer']);
        $this->assertDatabaseMissing('products', ['slug' => 'hearth-demo-1']);
    }

    public function test_uploaded_customizer_images_and_settings_are_shared_on_home_catalog_product_and_cart(): void
    {
        Storage::fake('public');
        $theme = $this->upload();
        app(ThemeManager::class)->activate($theme->name);
        $response = $this->postJson(route('admin.themes.uploads.store'), ['file' => UploadedFile::fake()->image('hero.png', 40, 20)])->assertOk();
        $value = $response->json('image.value');
        $this->assertIsString($value);
        $this->put(route('admin.themes.design', $theme), ['design' => [
            'custom.hero_heading' => 'A home of your own', 'custom.hero_image' => $value,
            'custom.catalog_columns' => '4', 'custom.product_button' => 'ADD TO YOUR BAG',
        ]])->assertSessionHasNoErrors();
        $variant = $this->sellable();
        foreach (['/', '/search', '/p/'.$variant->product->slug, '/cart'] as $url) {
            $this->get($url)->assertInertia(fn (Assert $page) => $page
                ->where('storefront.theme.name', 'ecomodular/hearth')
                ->where('storefront.theme.frontend.product', 'src/product.tsx')
                ->where('storefront.theme.settings.hero_heading', 'A home of your own')
                ->where('storefront.theme.settings.catalog_columns', '4')
                ->where('storefront.theme.settings.product_button', 'ADD TO YOUR BAG')
                ->where('storefront.theme.settings.hero_image', fn ($url) => is_string($url) && str_contains($url, '/storage/themes/')));
        }
    }

    public function test_stock_filter_uses_inventory_and_optional_demo_import_is_idempotent(): void
    {
        $theme = $this->upload();
        $manifest = ThemeManifest::fromArray($theme->manifest);
        $result = app(ThemeDemoImporter::class)->import($manifest, $this->owner());
        $this->assertSame(['categories' => 2, 'products' => 6], $result);
        $this->assertSame(['categories' => 0, 'products' => 0], app(ThemeDemoImporter::class)->import($manifest, $this->owner()));
        $inStock = $this->sellable('10', 4);
        $this->sellable('10', 0);
        $this->get('/search?in_stock=1')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 7)->where('filters.in_stock', '1')
            ->where('products.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['in_stock'])));
        $this->getJson('/search?in_stock=bad')->assertUnprocessable();
    }
}
