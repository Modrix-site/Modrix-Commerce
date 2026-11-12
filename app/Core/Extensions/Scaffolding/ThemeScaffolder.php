<?php

namespace App\Core\Extensions\Scaffolding;

use App\Core\Platform;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Starts a theme from an installed one (default: the bundled default theme):
 * copies its manifest, assets and optional browser source under a new name,
 * version 0.1.0. Browser entries need an asset rebuild after editing.
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

        // New packages own their schema and editor, even when starting from a
        // data-only theme. Keep copied custom editors and schemas when present.
        if (! isset($manifest['customizer_file'])) {
            File::put($directory.'/settings.json', json_encode($manifest['customizer'] ?? ['title' => $displayName.' design', 'fields' => new \stdClass], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $manifest['customizer_file'] = 'settings.json';
            unset($manifest['customizer']);
        }
        if (! isset($manifest['frontend']['customizer'])) {
            File::ensureDirectoryExists($directory.'/src');
            File::put($directory.'/src/customizer.tsx', <<<'TSX'
                import type { ThemeCustomizerProps } from '@/lib/theme-customizer';

                // Replace this component to build your own editor. The default
                // editor uses this package's settings.json schema and defaults.
                export default function Customizer({ DefaultCustomizer, ...props }: ThemeCustomizerProps) {
                    return <DefaultCustomizer {...props} />;
                }
                TSX);
            $manifest['frontend']['customizer'] = 'src/customizer.tsx';
        }

        File::put($directory.'/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $files = [];
        foreach (File::allFiles($directory) as $file) {
            $files[] = str_replace('\\', '/', $file->getRelativePathname());
        }
        sort($files);

        return $files;
    }
}
