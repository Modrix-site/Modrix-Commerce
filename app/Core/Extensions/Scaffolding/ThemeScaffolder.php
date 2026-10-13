<?php

namespace App\Core\Extensions\Scaffolding;

use App\Core\Platform;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Starts a theme from an installed one (default: the bundled default theme):
 * copies its manifest and images under a new name, version 0.1.0. Themes are
 * data only, so the copy is valid immediately and every value can be edited.
 */
final class ThemeScaffolder
{
    /**
     * @return list<string> Created files, relative to the package.
     */
    public function create(string $name, string $directory, string $baseDirectory, string $displayName, string $author): array
    {
        if (! preg_match('#^[a-z0-9]+(?:-[a-z0-9]+)*/[a-z0-9]+(?:-[a-z0-9]+)*$#', $name)) {
            throw new InvalidArgumentException('The name must look like "vendor/theme" (lowercase letters, digits and dashes).');
        }

        if (File::exists($directory)) {
            throw new InvalidArgumentException("{$directory} already exists.");
        }

        $manifest = json_decode((string) @file_get_contents($baseDirectory.'/theme.json'), true);
        if (! is_array($manifest)) {
            throw new InvalidArgumentException('The base theme has no readable theme.json.');
        }

        File::copyDirectory($baseDirectory, $directory);

        $manifest = array_merge($manifest, [
            'name' => $name,
            'display_name' => $displayName,
            'description' => "Describe {$displayName}: the stores and products it suits.",
            'version' => '0.1.0',
            'platform_version' => '^'.explode('.', Platform::VERSION)[0].'.0',
            'author' => $author,
            'license' => 'MIT',
        ]);

        File::put($directory.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $files = [];
        foreach (File::allFiles($directory) as $file) {
            $files[] = str_replace('\\', '/', $file->getRelativePathname());
        }
        sort($files);

        return $files;
    }
}
