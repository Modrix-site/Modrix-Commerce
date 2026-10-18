<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleException;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class ManifestValidationTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
    }

    /**
     * @return list<string>
     */
    private function errorsFor(string $name): array
    {
        app(ModuleRepository::class)->sync();
        $extension = Extension::query()->where('name', $name)->firstOrFail();
        $this->assertSame(ModuleState::Invalid, $extension->state, "{$name} should be invalid.");

        return (array) $extension->errors;
    }

    public function test_the_sample_fixture_is_valid()
    {
        $this->makeHelloModule();
        app(ModuleRepository::class)->sync();

        $extension = Extension::query()->where('name', 'acme/hello')->firstOrFail();
        $this->assertSame(ModuleState::Discovered, $extension->state);
        $this->assertNull($extension->errors);
    }

    public function test_package_logo_round_trips_and_is_shared_with_admin(): void
    {
        $path = $this->makeHelloModule(overrides: ['logo' => 'brand.png']);
        file_put_contents($path.'/brand.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aSAAAAABJRU5ErkJggg=='));
        app(ModuleRepository::class)->sync();
        $extension = Extension::where('name', 'acme/hello')->firstOrFail();
        $this->assertSame(ModuleState::Discovered, $extension->state);
        $this->assertSame('brand.png', $extension->manifest()->toArray()['logo']);
        $this->withoutVite();
        $this->actingAs($this->userWithRole('administrator'))->get(route('admin.extensions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('extensions.0.logo', fn ($logo) => str_starts_with($logo, 'data:image/png;base64,')));
    }

    public function test_logo_cannot_escape_package_or_load_remote_or_active_content(): void
    {
        foreach (['../outside.png', 'https://example.com/logo.png', 'brand.svg', 'missing.png'] as $logo) {
            $this->makeHelloModule(overrides: ['logo' => $logo]);
            $this->assertStringContainsString('logo must be a local', implode(' ', $this->errorsFor('acme/hello')));
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidManifests(): array
    {
        return [
            'unknown key' => [['eval' => 'phpinfo()'], 'Unknown manifest key "eval"'],
            'name mismatch' => [['name' => 'acme/other'], 'does not match the package directory'],
            'bad version' => [['version' => 'v1'], 'semantic version'],
            'wrong type' => [['type' => 'theme'], 'type must be "module"'],
            'incompatible platform' => [['platform_version' => '^9.0'], 'Requires platform version ^9.0'],
            'invalid platform constraint' => [['platform_version' => 'banana'], 'not a valid version constraint'],
            'unknown capability' => [['capabilities' => ['admin.routes', 'storefront.routes', 'admin.navigation', 'database.migrations', 'settings', 'permissions', 'filesystem.root']], 'Unknown capability "filesystem.root"'],
            'routes without capability' => [['capabilities' => ['admin.navigation', 'database.migrations', 'settings', 'permissions', 'storefront.routes']], 'requires the "admin.routes" capability'],
            'migrations without capability' => [['capabilities' => ['admin.routes', 'storefront.routes', 'admin.navigation', 'settings', 'permissions']], 'requires the "database.migrations" capability'],
            'path escape' => [['migrations' => '../../../database/migrations'], 'migrations must be an existing directory inside the package'],
            'absolute path' => [['routes' => ['admin' => '/etc/passwd']], 'must exist inside the package'],
            'reserved namespace' => [['autoload' => ['psr-4' => ['App\\Evil\\' => 'src/']], 'provider' => 'App\\Evil\\Provider'], 'reserved'],
            'provider outside namespace' => [['provider' => 'Other\\Vendor\\Provider'], 'inside the module\'s autoload namespace'],
            'missing provider file' => [['provider' => 'Acme\\Hello\\MissingProvider'], 'does not exist'],
            'core permission' => [['permissions' => [['name' => 'orders.refund', 'label' => 'x', 'group' => 'x']], 'admin_navigation' => []], 'already defined by core'],
            'bad permission name' => [['permissions' => [['name' => 'Bad Name', 'label' => 'x', 'group' => 'x']]], 'must look like'],
            'setting type' => [['settings' => [['key' => 'code', 'type' => 'php', 'label' => 'Code']]], 'type must be one of'],
            'setting default type' => [['settings' => [['key' => 'limit', 'type' => 'integer', 'label' => 'Limit', 'default' => 'many']]], 'default does not match its type'],
            'navigation without permission' => [['admin_navigation' => [['key' => 'hello', 'title' => 'Hello', 'route' => 'index']]], 'permission is required'],
            'navigation with foreign permission' => [['admin_navigation' => [['key' => 'hello', 'title' => 'Hello', 'route' => 'index', 'permission' => 'other.module.permission']]], 'must be one of the module\'s permissions'],
            'self dependency' => [['requires' => ['acme/hello' => '^1.0']], 'not a valid module name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidManifests')]
    public function test_invalid_manifests_are_rejected(array $overrides, string $expected)
    {
        $this->makeHelloModule(overrides: $overrides);

        $errors = implode("\n", $this->errorsFor('acme/hello'));

        $this->assertStringContainsString($expected, $errors);
    }

    public function test_malformed_json_and_oversized_manifests_are_rejected()
    {
        $this->writeFile($this->modulesPath.'/acme/broken-json/module.json', '{"name": "acme/broken-json",');
        $this->writeFile($this->modulesPath.'/acme/huge/module.json', str_repeat(' ', 70000).'{}');

        $this->assertStringContainsString('not valid JSON', implode(' ', $this->errorsFor('acme/broken-json')));
        $this->assertStringContainsString('larger than 64 KB', implode(' ', $this->errorsFor('acme/huge')));
    }

    public function test_conflicts_between_packages_are_rejected()
    {
        $this->makeHelloModule();
        $this->makeModule('acme/zz-copycat', [
            'permissions' => [['name' => 'hello.manage', 'label' => 'Steal', 'group' => 'x']],
            'capabilities' => ['permissions'],
        ]);

        $this->assertStringContainsString('already defined by acme/hello', implode(' ', $this->errorsFor('acme/zz-copycat')));
    }

    public function test_migration_names_must_not_collide_with_core()
    {
        $dir = $this->makeHelloModule();
        $core = basename((string) collect(glob(database_path('migrations/*.php')))->first());
        $this->writeFile($dir.'/database/migrations/'.$core, '<?php return null;');

        $this->assertStringContainsString('same name as one from core', implode(' ', $this->errorsFor('acme/hello')));
    }

    public function test_dependency_cycles_are_rejected()
    {
        $this->makeModule('acme/chicken', ['requires' => ['acme/egg' => '*']]);
        $this->makeModule('acme/egg', ['requires' => ['acme/chicken' => '*']]);

        $this->assertStringContainsString('Dependency cycle', implode(' ', $this->errorsFor('acme/chicken')));
        $this->assertStringContainsString('Dependency cycle', implode(' ', $this->errorsFor('acme/egg')));
    }

    public function test_invalid_packages_cannot_be_installed()
    {
        $this->makeHelloModule(overrides: ['platform_version' => '^9.0']);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('invalid manifest');
        app(ModuleManager::class)->install('acme/hello');
    }

    public function test_packages_outside_vendor_name_layout_are_ignored()
    {
        $this->writeFile($this->modulesPath.'/loose/module.json', '{}');
        $this->writeFile($this->modulesPath.'/a/b/c/module.json', '{}');

        app(ModuleRepository::class)->sync();

        $this->assertSame(0, Extension::query()->count());
    }
}
