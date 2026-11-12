<?php

namespace Tests\Feature\Catalog;

use App\Core\Localization\Locales;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private Product $product;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        app(Locales::class)->update('en', ['es', 'fr']);

        $this->category = Category::factory()->create(['name' => 'Shirts', 'slug' => 'shirts']);
        $variant = $this->sellable(product: ['name' => 'Linen shirt', 'slug' => 'linen-shirt', 'short_description' => 'Light and cool', 'description' => 'Washed linen.']);
        $this->product = $variant->product;
        $this->product->categories()->attach($this->category->id);
    }

    private function translate(): void
    {
        $this->actingAs($this->owner());
        $this->put(route('admin.products.translations.update', [$this->product, 'es']), [
            'name' => 'Camisa de lino', 'short_description' => 'Ligera y fresca', 'description' => '',
        ])->assertRedirect();
        $this->put(route('admin.categories.translations.update', [$this->category, 'es']), ['name' => 'Camisas'])->assertRedirect();
        auth()->logout();
    }

    public function test_languages_are_configured_by_settings_managers()
    {
        $this->actingAs($this->userWithRole('catalog-manager'))->put(route('admin.settings.languages.update'), ['default' => 'en', 'additional' => ['de']])->assertForbidden();

        $this->actingAs($this->owner())->get(route('admin.settings.languages.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('default', 'en')->where('additional', ['es', 'fr']));
        $this->put(route('admin.settings.languages.update'), ['default' => 'en', 'additional' => ['xx'], 'mode' => 'manual'])->assertSessionHasErrors('additional.0');
        $this->put(route('admin.settings.languages.update'), ['default' => 'es', 'additional' => ['es', 'de'], 'mode' => 'manual'])->assertSessionHasNoErrors();
        $this->assertSame(['es', 'de'], app(Locales::class)->enabled());
    }

    public function test_the_translation_editor_saves_and_clears_translations()
    {
        $this->actingAs($this->userWithRole('catalog-manager'));
        $this->get(route('admin.products.translations.edit', $this->product))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/translations/edit')
                ->where('source.name', 'Linen shirt')->has('locales', 2)->where('locales.0.code', 'es'));

        $this->put(route('admin.products.translations.update', [$this->product, 'es']), ['name' => 'Camisa de lino'])->assertRedirect();
        $this->assertDatabaseHas('product_translations', ['product_id' => $this->product->id, 'locale' => 'es', 'name' => 'Camisa de lino']);

        // A language that is not enabled cannot be written.
        $this->put(route('admin.products.translations.update', [$this->product, 'de']), ['name' => 'Leinenhemd'])->assertNotFound();

        // Clearing every field removes the translation.
        $this->put(route('admin.products.translations.update', [$this->product, 'es']), ['name' => ''])->assertRedirect();
        $this->assertDatabaseMissing('product_translations', ['product_id' => $this->product->id]);

        $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.products.translations.edit', $this->product))->assertForbidden();
    }

    public function test_the_overview_shows_progress_and_what_is_missing()
    {
        $this->translate();

        $this->actingAs($this->owner())->get(route('admin.translations.index', ['locale' => 'fr']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.0.code', 'es')->where('stats.0.products', 1)->where('stats.0.categories', 1)
                ->where('stats.1.products', 0)
                ->where('missing.data.0.name', 'Linen shirt'));
    }

    public function test_shoppers_see_translations_with_fallback_to_the_default_language()
    {
        $this->translate();

        // Default language.
        $this->get(route('storefront.product', 'linen-shirt'))
            ->assertInertia(fn (Assert $page) => $page->where('product.name', 'Linen shirt')->where('storefront.locale', 'en'));

        // Spanish: translated fields, and the default text where the translation is empty.
        $this->get(route('storefront.product', ['slug' => 'linen-shirt', 'lang' => 'es']))
            ->assertCookie('storefront_locale')
            ->assertInertia(fn (Assert $page) => $page
                ->where('storefront.locale', 'es')
                ->where('product.name', 'Camisa de lino')
                ->where('product.short_description', 'Ligera y fresca')
                ->where('product.description', 'Washed linen.')
                ->where('breadcrumbs.0.name', 'Camisas')
                ->where('storefront.categories.0.name', 'Camisas'));

        // The choice is remembered.
        $this->withCookie('storefront_locale', 'es')->get(route('storefront.category', 'shirts'))
            ->assertInertia(fn (Assert $page) => $page->where('title', 'Camisas')->where('products.data.0.name', 'Camisa de lino'));

        // The browser's language is used when nothing was chosen; disabled languages are ignored.
        $this->get('/', ['Accept-Language' => 'es-ES,es;q=0.9'])->assertInertia(fn (Assert $page) => $page->where('storefront.locale', 'es'));
        // (the cookie from above still says Spanish, which is kept).
        $this->get(route('storefront.product', ['slug' => 'linen-shirt', 'lang' => 'de']))
            ->assertInertia(fn (Assert $page) => $page->where('storefront.locale', 'es')->where('storefront.locales.1.code', 'es'));
    }

    public function test_search_and_cart_use_the_shoppers_language()
    {
        $this->translate();

        $this->withCookie('storefront_locale', 'es')->get(route('storefront.search', ['q' => 'camisa']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Camisa de lino'));

        $this->addToCart($this->product->variants()->firstOrFail());
        $this->withCookie('storefront_locale', 'es')->get(route('cart.show'))
            ->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.product_name', 'Camisa de lino'));
    }
}
