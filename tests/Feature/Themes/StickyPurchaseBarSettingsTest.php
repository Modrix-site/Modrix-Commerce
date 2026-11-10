<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

/**
 * The sticky purchase bar on product pages: store-wide customizer settings
 * (Product tab) that every theme's StickyAddToCart reads.
 */
class StickyPurchaseBarSettingsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        $this->makeTheme('acme/sunny');
        $this->makeTheme('acme/moon');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('acme/sunny');
    }

    private function productUrl(): string
    {
        return route('storefront.product', $this->sellable('25.00')->product->slug);
    }

    public function test_untouched_store_lets_the_theme_decide()
    {
        $this->get($this->productUrl())->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.pages.product.sticky_bar', null)
            ->where('storefront.theme.pages.product.sticky_bar_buttons', 'both')
            ->where('storefront.theme.pages.product.sticky_bar_options', true));
    }

    public function test_customizer_save_reaches_the_product_page_in_any_theme()
    {
        $theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();

        $this->actingAs($this->owner())->put(route('admin.themes.design', $theme), ['design' => [
            'product.sticky_bar' => 'everywhere',
            'product.sticky_bar_buttons' => 'add_to_cart',
            'product.sticky_bar_options' => false,
        ]])->assertSessionHasNoErrors();

        $url = $this->productUrl();
        $expect = fn (Assert $page) => $page
            ->where('storefront.theme.pages.product.sticky_bar', 'everywhere')
            ->where('storefront.theme.pages.product.sticky_bar_buttons', 'add_to_cart')
            ->where('storefront.theme.pages.product.sticky_bar_options', false);

        $this->get($url)->assertInertia($expect);

        // Store-wide: a theme switch keeps the merchant's choice.
        app(ThemeManager::class)->activate('acme/moon');
        $this->get($url)->assertInertia($expect);
    }

    public function test_switching_it_off()
    {
        $theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();

        $this->actingAs($this->owner())->put(route('admin.themes.design', $theme), ['design' => [
            'product.sticky_bar' => 'off',
        ]])->assertSessionHasNoErrors();

        $this->get($this->productUrl())->assertInertia(fn (Assert $page) => $page
            ->where('storefront.theme.pages.product.sticky_bar', 'off'));
    }

    public function test_unknown_values_are_rejected()
    {
        $theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('admin.themes.design', $theme), ['design' => ['product.sticky_bar' => 'tablets']])
            ->assertSessionHasErrors();
        $this->actingAs($owner)->put(route('admin.themes.design', $theme), ['design' => ['product.sticky_bar_buttons' => 'wishlist']])
            ->assertSessionHasErrors();
    }

    public function test_customizer_lists_the_fields_on_the_product_tab()
    {
        $theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();

        $this->actingAs($this->owner())->get(route('admin.themes.customize', $theme))->assertInertia(fn (Assert $page) => $page
            ->where('design.defaults', fn ($defaults) => ($defaults['product.sticky_bar_buttons'] ?? null) === 'both'
                && ($defaults['product.sticky_bar_options'] ?? null) === true
                && ! array_key_exists('product.sticky_bar', (array) $defaults)));
    }
}
