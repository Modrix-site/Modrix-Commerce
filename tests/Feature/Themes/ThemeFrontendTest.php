<?php

namespace Tests\Feature\Themes;

use App\Core\Themes\ThemeManifestValidator;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;

class ThemeFrontendTest extends TestCase
{
    use ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpThemes();
    }

    public function test_package_owned_layout_and_nested_pages_are_valid_without_core_registration(): void
    {
        $frontend = [
            'layout' => 'src/layout.tsx',
            'customizer' => 'src/customizer.tsx',
            'stylesheet' => 'src/style.css',
            'pages' => [
                'storefront/checkout' => 'src/pages/checkout.tsx',
                'storefront/account/orders' => 'src/pages/account/orders.tsx',
            ],
        ];
        $directory = $this->makeTheme(overrides: ['frontend' => $frontend]);
        foreach (['src/layout.tsx', 'src/customizer.tsx', 'src/style.css', ...array_values($frontend['pages'])] as $path) {
            File::ensureDirectoryExists(dirname($directory.'/'.$path));
            File::put($directory.'/'.$path, '');
        }
        [$manifest, $errors] = app(ThemeManifestValidator::class)->validate($directory, 'acme/sunny', json_decode(File::get($directory.'/theme.json'), true));
        $this->assertSame([], $errors);
        $this->assertSame($frontend, $manifest->frontend);
    }

    #[DataProvider('invalidEntries')]
    public function test_invalid_entry_shapes_and_paths_are_rejected(mixed $frontend): void
    {
        $directory = $this->makeTheme(overrides: ['frontend' => $frontend]);
        [$manifest, $errors] = app(ThemeManifestValidator::class)->validate($directory, 'acme/sunny', json_decode(File::get($directory.'/theme.json'), true));
        $this->assertNull($manifest);
        $this->assertNotEmpty($errors);
    }

    public static function invalidEntries(): array
    {
        return [
            'scalar' => ['invalid'],
            'unknown entry' => [['server' => 'src/server.php']],
            'scalar pages' => [['pages' => 'src/pages/cart.tsx']],
            'list pages' => [['pages' => ['src/pages/cart.tsx']]],
            'admin page' => [['pages' => ['admin/dashboard' => 'src/pages/dashboard.tsx']]],
            'traversal' => [['pages' => ['storefront/cart' => 'src/pages/../../../cart.tsx']]],
            'server script' => [['pages' => ['storefront/cart' => 'src/pages/cart.php']]],
            'missing component' => [['pages' => ['storefront/cart' => 'src/pages/missing.tsx']]],
            'invalid layout' => [['layout' => '../layout.tsx']],
            'missing stylesheet' => [['stylesheet' => 'src/style.css']],
            'customizer traversal' => [['customizer' => '../customizer.tsx']],
            'missing customizer' => [['customizer' => 'src/customizer.tsx']],
        ];
    }

    public function test_declared_browser_entries_still_reject_server_code(): void
    {
        $directory = $this->makeTheme(overrides: ['frontend' => ['layout' => 'src/layout.tsx']]);
        File::ensureDirectoryExists($directory.'/src');
        File::put($directory.'/src/layout.tsx', '');
        File::put($directory.'/src/server.php', '<?php');
        [$manifest, $errors] = app(ThemeManifestValidator::class)->validate($directory, 'acme/sunny', json_decode(File::get($directory.'/theme.json'), true));
        $this->assertNull($manifest);
        $this->assertStringContainsString('src/server.php', implode(' ', $errors));
    }
}
