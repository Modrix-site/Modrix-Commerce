<?php

namespace Tests\Feature\Marketplace;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\Client\StoreLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class LicensedPackageVisibilityTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
        $this->setUpModules();
    }

    private function license(string $name, string $type): StoreLicense
    {
        return StoreLicense::query()->forceCreate([
            'product_slug' => str_replace('/', '-', $name),
            'product_name' => $name,
            'extension_name' => $name,
            'type' => $type,
            'key_encrypted' => encrypt('test-key', false),
            'key_hint' => 'AAAAA',
            'status' => 'active',
        ]);
    }

    public function test_licensed_theme_is_identified_in_appearance_without_switching_the_store_theme(): void
    {
        $this->makeTheme();
        $this->actingAs($this->owner())->get(route('admin.themes.index'))
            ->assertInertia(fn (Assert $page) => $page->has('licensedThemes', 0)
                ->where('themes.0.name', 'acme/sunny')->where('themes.0.state', 'available'));

        $license = $this->license('acme/sunny', 'theme');
        $this->license('acme/hello', 'module');
        $this->get(route('admin.themes.index'))->assertInertia(fn (Assert $page) => $page
            ->where('licensedThemes', ['acme/sunny'])->where('themes.0.name', 'acme/sunny')
            ->where('themes.0.state', 'available'));
        $this->assertDatabaseMissing('extensions', ['type' => 'theme', 'state' => ModuleState::Enabled->value]);

        $license->forceFill(['status' => 'revoked'])->save();
        $this->get(route('admin.themes.index'))->assertInertia(fn (Assert $page) => $page->has('licensedThemes', 0));
    }

    public function test_licensed_module_is_identified_in_extensions_without_installing_or_enabling_it(): void
    {
        $this->makeHelloModule();
        $license = $this->license('acme/hello', 'module');
        $this->license('acme/sunny', 'theme');
        $this->actingAs($this->owner())->get(route('admin.extensions.index'))->assertInertia(fn (Assert $page) => $page
            ->where('licensedExtensions', ['acme/hello'])->where('extensions.0.name', 'acme/hello')
            ->where('extensions.0.state', 'discovered'));
        $this->assertSame(ModuleState::Discovered, Extension::query()->where('name', 'acme/hello')->firstOrFail()->state);

        $license->delete();
        $this->get(route('admin.extensions.index'))->assertInertia(fn (Assert $page) => $page->has('licensedExtensions', 0));
    }
}
