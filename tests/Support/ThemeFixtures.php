<?php

namespace Tests\Support;

use App\Core\Marketplace\PackageArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Writes theme packages into a throwaway themes directory.
 */
trait ThemeFixtures
{
    protected string $themesPath;

    protected function setUpThemes(bool $withBundled = false): void
    {
        $this->themesPath = storage_path('framework/testing/themes-'.Str::random(10));
        File::ensureDirectoryExists($this->themesPath);

        if ($withBundled) {
            File::copyDirectory(base_path('themes'), $this->themesPath);
        }

        config(['ecomodular.themes.path' => $this->themesPath]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->themesPath));
    }

    /**
     * @param  array<string, mixed>  $overrides  manifest keys to replace (null removes a key)
     */
    protected function makeTheme(string $name = 'acme/sunny', array $overrides = [], bool $withImage = true): string
    {
        $dir = $this->themesPath.'/'.$name;
        File::ensureDirectoryExists($dir.'/assets/images');

        if ($withImage) {
            $this->writePng($dir.'/assets/images/hero.png');
        }

        $manifest = array_filter(array_merge([
            'name' => $name,
            'display_name' => Str::headline(Str::after($name, '/')),
            'version' => '1.0.0',
            'type' => 'theme',
            'theme_type' => 'storefront',
            'platform_version' => '^0.4 || ^1.0',
            'capabilities' => ['home.sections'],
            'assets' => 'assets',
            'design' => [
                'color.primary' => '#ff6600',
                'font_heading' => 'serif',
                'layout.header' => 'centered',
                'layout.grid_columns' => '3',
            ],
            'sections' => ['home' => [
                ['type' => 'hero', 'settings' => ['heading' => 'Sunny days', 'image' => 'asset:assets/images/hero.png', 'button_label' => 'Shop', 'button_link' => '/search']],
                ['type' => 'featured_products', 'settings' => ['title' => 'Picks', 'limit' => 4]],
            ]],
            'changelog' => [['version' => '1.0.0', 'date' => '2026-01-01', 'notes' => 'First release.']],
        ], $overrides), fn ($v) => $v !== null);

        File::put($dir.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $dir;
    }

    /**
     * A ZIP of a theme bundled in the repository, built the way the package
     * command builds it, so upload tests do not depend on files in dist/.
     */
    protected function bundledThemeZip(string $slug): string
    {
        $path = storage_path('framework/testing/'.$slug.'-'.Str::random(8).'.zip');
        File::ensureDirectoryExists(dirname($path));
        PackageArchive::create(base_path('themes/modrixcommerce/'.$slug), $path);
        $this->beforeApplicationDestroyed(fn () => File::delete($path));

        return $path;
    }

    protected function writePng(string $path, int $width = 8, int $height = 8): void
    {
        File::ensureDirectoryExists(dirname($path));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 170, 0));
        imagepng($image, $path);
        imagedestroy($image);
    }
}
