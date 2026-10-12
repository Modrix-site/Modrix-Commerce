<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Enforces the layering rules from docs/architecture/overview.md:
 * platform core and commerce domains must not depend on the presentation
 * layer (Inertia, HTTP controllers) so they stay reusable from the API,
 * console, queues and modules.
 */
class LayerBoundariesTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function rules(): array
    {
        return [
            'core is presentation-free' => ['app/Core', ['Inertia\\', 'App\\Http\\']],
            'domains are presentation-free' => ['app/Domains', ['Inertia\\', 'App\\Http\\']],
        ];
    }

    /**
     * @param  list<string>  $forbidden
     */
    #[DataProvider('rules')]
    public function test_layer_does_not_import_forbidden_namespaces(string $directory, array $forbidden)
    {
        $path = dirname(__DIR__, 2).'/'.$directory;

        if (! is_dir($path)) {
            $this->assertTrue(true, "{$directory} does not exist yet.");

            return;
        }

        $violations = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach ($forbidden as $namespace) {
                if (preg_match('/^use\s+'.preg_quote($namespace, '/').'/m', $source)
                    || str_contains($source, '\\'.$namespace)) {
                    $violations[] = str_replace($path, $directory, $file->getPathname())." uses {$namespace}";
                }
            }
        }

        $this->assertSame([], $violations);
    }
}
