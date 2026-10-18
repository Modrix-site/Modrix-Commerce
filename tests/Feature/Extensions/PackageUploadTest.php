<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Marketplace\PackageArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Support\ModuleFixtures;
use Tests\Support\ThemeFixtures;
use Tests\TestCase;
use ZipArchive;

class PackageUploadTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase, ThemeFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpThemes();
        $this->setUpModules();
    }

    private function archive(string $dir): UploadedFile
    {
        $path = $this->themesPath.'/upload-'.bin2hex(random_bytes(5)).'.zip';
        PackageArchive::create($dir, $path);
        File::deleteDirectory($dir);

        return new UploadedFile($path, 'package.zip', 'application/zip', null, true);
    }

    public function test_theme_upload_is_discovered_without_activation_and_duplicates_are_kept(): void
    {
        $file = $this->archive($this->makeTheme());
        $this->actingAs($this->userWithRole('administrator'))
            ->post(route('admin.themes.packages.store'), ['package' => $file])->assertSessionHasNoErrors()->assertRedirect(route('admin.themes.index'));
        $theme = Extension::query()->where('name', 'acme/sunny')->firstOrFail();
        $this->assertSame('discovered', $theme->state->value);
        $this->assertNull($theme->applied_manifest);
        $this->assertFileExists($this->themesPath.'/acme/sunny/theme.json');
        $before = File::get($this->themesPath.'/acme/sunny/theme.json');
        $this->post(route('admin.themes.packages.store'), ['package' => $file])->assertSessionHasErrors('package');
        $this->assertSame($before, File::get($this->themesPath.'/acme/sunny/theme.json'));
    }

    public function test_module_upload_does_not_install_or_execute_provider(): void
    {
        $file = $this->archive($this->makeHelloModule());
        $this->actingAs($this->userWithRole('administrator'))->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.extensions.packages.store'), ['package' => $file])->assertSessionHasNoErrors();
        $module = Extension::query()->where('name', 'acme/hello')->firstOrFail();
        $this->assertSame('discovered', $module->state->value);
        $this->assertNull($module->installed_at);
        $this->assertFalse(app()->bound('acme.hello.booted'));
    }

    public function test_traversal_and_executable_theme_files_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $dir = $this->makeTheme();
        File::put($dir.'/bad.php', '<?php echo "bad";');
        $this->post(route('admin.themes.packages.store'), ['package' => $this->archive($dir)])->assertSessionHasErrors('package');
        $file = $this->archive($this->makeTheme());
        $zip = new ZipArchive;
        $zip->open($file->getPathname());
        $zip->addFromString('../escape.txt', 'unsafe');
        $zip->close();
        $this->post(route('admin.themes.packages.store'), ['package' => $file])->assertSessionHasErrors('package');
        $this->assertDirectoryDoesNotExist($this->themesPath.'/acme/sunny');
    }

    public function test_uploads_require_permissions_and_module_password_confirmation(): void
    {
        $this->actingAs($this->userWithRole('store-manager'))
            ->post(route('admin.themes.packages.store'))->assertForbidden();
        $this->post(route('admin.extensions.packages.store'))->assertForbidden();
        $this->actingAs($this->userWithRole('administrator'))
            ->post(route('admin.extensions.packages.store'))->assertRedirect(route('password.confirm'));
    }
}
