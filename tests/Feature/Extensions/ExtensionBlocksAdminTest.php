<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\BlockPlacements;
use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Themes\ThemeManager;
use App\Core\Themes\ThemeRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ModuleFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ExtensionBlocksAdminTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase, ThemeFixtures;

    private Extension $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpThemes();
        $this->makeTheme('acme/sunny');
        app(ThemeRepository::class)->sync();
        app(ThemeManager::class)->activate('acme/sunny');
        $this->theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();

        $this->setUpModules();
        $this->makeHelloModule(overrides: ['settings' => [
            ['key' => 'greeting', 'type' => 'string', 'label' => 'Greeting', 'default' => 'Hi', 'tab' => 'product'],
            ['key' => 'limit', 'type' => 'integer', 'label' => 'Limit', 'default' => 5, 'min' => 1, 'max' => 10],
        ]]);
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('acme/hello');
        app(ModuleManager::class)->enable('acme/hello', trusted: true);

        app(StorefrontSlots::class)->block('acme/hello', 'banner', 'Hello banner', 'Banner', ['product.after_details', 'product.after_price'], 'product.after_details', fn () => []);
    }

    public function test_customize_page_lists_blocks_and_tab_settings()
    {
        $this->actingAs($this->owner())->get(route('admin.themes.customize', $this->theme))->assertInertia(fn (Assert $page) => $page
            ->where('blocks', fn ($blocks) => collect($blocks)->contains(fn ($b) => $b['id'] === 'acme/hello:banner'
                && $b['place'] === 'product.after_details' && $b['enabled'] === true
                && collect($b['places'])->pluck('key')->all() === ['product.after_details', 'product.after_price']
                && collect($b['places'])->firstWhere('key', 'product.after_price')['tab'] === 'product'))
            ->where('extensionSettings.product', fn ($groups) => count($groups) === 1
                && $groups[0]['module'] === 'acme/hello'
                && collect($groups[0]['fields'])->pluck('key')->all() === ['greeting']
                && $groups[0]['values']['greeting'] === 'Hi')
            ->where('extensionSettings.cart', []));
    }

    public function test_save_blocks_stores_store_wide()
    {
        $this->actingAs($this->owner())->put(route('admin.themes.blocks.update'), ['blocks' => [
            'acme/hello:banner' => ['place' => 'product.after_price', 'order' => 5, 'enabled' => false],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(['place' => 'product.after_price', 'order' => 5, 'enabled' => false], app(BlockPlacements::class)->for('acme/hello:banner'));
    }

    public function test_save_rejects_unknown_block_or_place()
    {
        $owner = $this->owner();

        foreach ([
            ['acme/forged:x' => ['place' => 'product.after_price', 'order' => 1, 'enabled' => true]],
            ['acme/hello:banner' => ['place' => 'cart.after_lines', 'order' => 1, 'enabled' => true]],
            ['acme/hello:banner' => ['place' => 'product.after_price', 'order' => 5000, 'enabled' => true]],
        ] as $blocks) {
            $this->actingAs($owner)->put(route('admin.themes.blocks.update'), ['blocks' => $blocks])->assertSessionHasErrors();
        }

        $this->assertSame([], app(BlockPlacements::class)->all());
    }

    public function test_tab_settings_keep_other_module_settings()
    {
        $manifest = app(ModuleRepository::class)->find('acme/hello')->manifest();
        app(ModuleSettingsSchema::class)->save($manifest, ['greeting' => 'Hi', 'limit' => 9]);

        $this->actingAs($this->owner())->put(route('admin.themes.extension-settings.update', ['module' => 'acme/hello']), ['settings' => ['greeting' => 'Welcome', 'limit' => 1]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $values = app(ModuleSettingsSchema::class)->values($manifest);
        $this->assertSame('Welcome', $values['greeting']);
        // "limit" has no tab: the customizer cannot change it.
        $this->assertSame(9, $values['limit']);
    }

    public function test_invalid_tab_in_manifest_is_rejected()
    {
        $this->makeModule('acme/tabbed', [
            'capabilities' => ['settings'],
            'settings' => [['key' => 'note', 'type' => 'boolean', 'label' => 'Note', 'default' => true, 'tab' => 'nowhere']],
        ]);
        app(ModuleRepository::class)->sync();
        $extension = Extension::query()->where('name', 'acme/tabbed')->firstOrFail();

        $this->assertContains('settings[0].tab must be one of general, header, home, catalog, product, cart, checkout, footer.', (array) $extension->errors);
    }

    public function test_customers_cannot_save_blocks()
    {
        $this->actingAs(User::factory()->create())->put(route('admin.themes.blocks.update'), ['blocks' => []])->assertForbidden();
    }
}
