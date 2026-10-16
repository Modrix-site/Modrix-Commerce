<?php

namespace Tests\Feature\Themes;

use App\Core\Extensions\Models\Extension;
use App\Core\Themes\CustomizerTabs;
use App\Core\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class CustomizerTabsTest extends TestCase
{
    use RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
    }

    private function tab(string $group, string $key = 'custom.x', array $extra = []): string
    {
        return CustomizerTabs::for($key, ['type' => 'string', 'label' => 'X', 'group' => $group, ...$extra]);
    }

    public function test_groups_map_to_tabs()
    {
        $expected = [
            // Hearth, Vellaro and Marketplace group names.
            'Homepage hero' => 'home', 'Categories' => 'home', 'Promotions' => 'home', 'Shopping shortcuts' => 'home',
            'Collection story' => 'home', 'Brand story' => 'home', 'Store assurances' => 'home', 'Campaign 3' => 'home',
            'Collections' => 'home', 'Marketplace homepage' => 'home',
            'Catalog' => 'catalog', 'Marketplace catalog' => 'catalog', 'Aurora catalog' => 'catalog',
            'Product page' => 'product', 'Product' => 'product',
            'Cart' => 'cart', 'Marketplace cart' => 'cart',
            'Header' => 'header', 'Marketplace header' => 'header', 'Navigation' => 'header',
            'Footer' => 'footer',
            'Colors' => 'general', 'Typography' => 'general', 'Layout' => 'general', 'Branding' => 'general', 'Shape and spacing' => 'general',
        ];

        foreach ($expected as $group => $tab) {
            $this->assertSame($tab, $this->tab($group), "Group \"{$group}\"");
        }
    }

    public function test_explicit_tab_wins()
    {
        $this->assertSame('footer', $this->tab('Cart', extra: ['tab' => 'footer']));
        // An unknown tab is ignored.
        $this->assertSame('cart', $this->tab('Cart', extra: ['tab' => 'nowhere']));
    }

    public function test_style_fields_follow_their_area()
    {
        $this->assertSame('general', $this->tab('Fonts and buttons', 'style.general.button_bg'));
        $this->assertSame('catalog', $this->tab('Fonts and buttons', 'style.catalog.button_bg'));
        $this->assertSame('home', $this->tab('Fonts and buttons', 'style.home.heading_size'));
    }

    public function test_customize_page_sends_a_tab_per_field()
    {
        $this->makeTheme();
        app(ThemeRepository::class)->sync();
        $theme = Extension::query()->where('type', 'theme')->where('name', 'acme/sunny')->firstOrFail();

        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.themes.customize', $theme))
            ->assertInertia(fn (Assert $page) => $page
                ->where('design.fields', fn ($fields) => collect($fields)->every(fn ($f) => in_array($f['tab'] ?? null, array_keys(CustomizerTabs::TABS), true))
                    && collect($fields)->firstWhere('key', 'color.primary')['tab'] === 'general'
                    && collect($fields)->firstWhere('key', 'contact_email')['tab'] === 'footer'
                    && collect($fields)->firstWhere('key', 'help_link')['tab'] === 'header'));
    }
}
