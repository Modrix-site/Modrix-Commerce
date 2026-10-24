<?php

/**
 * Reads themes and extensions for the marketplace.
 * Published packages are copied into marketplace storage, so deleting one
 * from a store's Appearance or Extensions screen leaves the catalog copy.
 */
final class Catalog
{
    public function __construct(private readonly string $root) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        $indexed = [];
        foreach ($this->scan($this->root) as $product) {
            $indexed[$product['extension']] = $product;
            $this->retain($product);
        }

        $library = $this->libraryRoot();
        if ($library !== null) {
            $this->materializeArchived($indexed);
            foreach ($this->scan($library) as $product) {
                $indexed[$product['extension']] ??= $product;
            }
        }

        $products = array_values($indexed);
        usort($products, fn (array $a, array $b) => [$a['type'], $a['name']] <=> [$b['type'], $b['name']]);

        return $products;
    }

    /**
     * Copies a package into marketplace storage. A later delete of the store
     * install does not remove this copy. Pass $replace after publishing a new zip.
     *
     * @param  array<string, mixed>  $product
     */
    public function retain(array $product, bool $replace = false): void
    {
        $source = $product['directory'] ?? null;
        $dest = $this->libraryPackage((string) ($product['type'] ?? ''), (string) ($product['extension'] ?? ''));
        if ($dest === null || ! is_string($source) || ! is_dir($source)) {
            return;
        }

        $sourceReal = realpath($source);
        $destReal = realpath($dest);
        if ($sourceReal !== false && $destReal !== false && $sourceReal === $destReal) {
            return;
        }
        if (is_dir($dest) && ! $replace) {
            return;
        }
        if (is_dir($dest)) {
            $this->removeDirectory($dest);
        }

        $this->copyDirectory($source, $dest);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        foreach ($this->products() as $product) {
            if ($product['slug'] === $slug) {
                return $product;
            }
        }

        return null;
    }

    /**
     * The shape Admin → Marketplace already expects.
     *
     * @param  list<array<string, mixed>>  $products
     * @return array{products: list<array<string, mixed>>}
     */
    public function api(string $base, array $products, string $currency): array
    {
        $base = rtrim($base, '/');

        return [
            'products' => array_map(function (array $product) use ($base, $currency) {
                $amount = number_format((float) ($product['price'] ?? 0), 2, '.', '');
                $free = ($product['premium'] ?? false) !== true;

                return [
                    'slug' => $product['slug'],
                    'name' => $product['name'],
                    'summary' => $product['summary'],
                    'type' => $product['type'],
                    'extension' => $product['extension'],
                    'price' => [
                        'amount' => $free ? '0.00' : $amount,
                        'currency' => $currency,
                        'formatted' => $free ? 'Free' : $currency.' '.$amount,
                        'display_amount' => $free ? '0.00' : $amount,
                        'display_currency' => $currency,
                    ],
                    'free' => $free,
                    'latest_version' => $product['version'],
                    'platform_version' => $product['platform_version'],
                    'url' => $base.'/item/'.$product['slug'],
                    'thumbnail' => ($product['thumbnail'] ?? '') !== '' ? $base.$product['thumbnail'] : ($product['screenshot'] !== null ? $base.'/media/'.$product['slug'] : null),
                    'repository_url' => $product['repository_url'] ?? '',
                    'demo_url' => $product['demo_url'] ?? '',
                    'categories' => array_values($product['category_labels'] ?? []),
                    'featured' => (bool) ($product['featured'] ?? false),
                    'download_url' => empty($product['package']) ? null : $base.'/download/'.$product['slug'],
                ];
            }, $products),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function product(string $file, string $type): ?array
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data) || ! is_string($data['name'] ?? null) || ! is_string($data['display_name'] ?? null)) {
            return null;
        }

        $directory = dirname($file);
        $screenshot = null;
        if (is_string($data['screenshot'] ?? null)) {
            $candidate = $directory.'/'.ltrim(str_replace('\\', '/', $data['screenshot']), '/');
            $real = realpath($candidate);
            $base = realpath($directory);
            if (is_string($real) && is_string($base) && str_starts_with($real, $base.DIRECTORY_SEPARATOR) && is_file($real)) {
                $screenshot = $real;
            }
        }

        return [
            'directory' => $directory,
            'slug' => str_replace('/', '-', $data['name']),
            'name' => $data['display_name'],
            'summary' => is_string($data['description'] ?? null) ? $data['description'] : null,
            'type' => $type,
            'extension' => $data['name'],
            'version' => is_string($data['version'] ?? null) ? $data['version'] : '1.0.0',
            'platform_version' => is_string($data['platform_version'] ?? null) ? $data['platform_version'] : '^1.0',
            'author' => is_string($data['author'] ?? null) ? $data['author'] : 'Modrix team',
            'license' => is_string($data['license'] ?? null) ? $data['license'] : 'MIT',
            'screenshot' => $screenshot,
            'updated_at' => date('c', (int) filemtime($file)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scan(string $root): array
    {
        $products = [];
        foreach (glob($root.'/themes/*/*/theme.json') ?: [] as $file) {
            $product = $this->product($file, 'theme');
            if ($product !== null) {
                $products[] = $product;
            }
        }
        foreach (glob($root.'/modules/*/*/module.json') ?: [] as $file) {
            $product = $this->product($file, 'module');
            if ($product !== null) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /** Marketplace-owned package tree. Null when catalog storage is not loaded. */
    private function libraryRoot(): ?string
    {
        if (! function_exists('catalogStorage')) {
            return null;
        }

        return catalogStorage().'/library';
    }

    private function libraryPackage(string $type, string $extension): ?string
    {
        $folder = $type === 'theme' ? 'themes' : ($type === 'module' ? 'modules' : '');
        if ($folder === '' || ! preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $extension)) {
            return null;
        }
        $root = $this->libraryRoot();

        return $root === null ? null : $root.'/'.$folder.'/'.$extension;
    }

    /**
     * Restores catalog copies from published zips when the store directory is gone.
     *
     * @param  array<string, array<string, mixed>>  $indexed
     */
    private function materializeArchived(array $indexed): void
    {
        $storage = function_exists('catalogStorage') ? catalogStorage() : '';
        if ($storage === '' || ! is_dir($storage.'/sources')) {
            return;
        }

        $latest = [];
        foreach (glob($storage.'/sources/{theme,module}/*/*/*.zip', GLOB_BRACE) ?: [] as $zip) {
            $version = basename($zip, '.zip');
            $name = basename(dirname($zip));
            $vendor = basename(dirname($zip, 2));
            $type = basename(dirname($zip, 3)) === 'theme' ? 'theme' : 'module';
            $extension = $vendor.'/'.$name;
            if (isset($indexed[$extension]) || ! preg_match('#^[0-9A-Za-z][0-9A-Za-z._-]*$#', $version)) {
                continue;
            }
            if (! isset($latest[$extension]) || version_compare($version, $latest[$extension]['version'], '>')) {
                $latest[$extension] = ['type' => $type, 'version' => $version, 'zip' => $zip];
            }
        }

        foreach ($latest as $extension => $archive) {
            $dest = $this->libraryPackage($archive['type'], $extension);
            if ($dest === null || is_dir($dest)) {
                continue;
            }
            $this->extractZip($archive['zip'], $dest);
        }
    }

    private function copyDirectory(string $source, string $dest): void
    {
        if (! is_dir($dest) && ! mkdir($dest, 0775, true) && ! is_dir($dest)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $this->skipped($source, $file->getPathname())) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(rtrim(str_replace('\\', '/', $source), '/')) + 1);
            $target = $dest.'/'.$relative;
            if ($file->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0775, true);
                }

                continue;
            }
            if (! $file->isFile()) {
                continue;
            }
            $parent = dirname($target);
            if (! is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            copy($file->getPathname(), $target);
        }
    }

    private function extractZip(string $zipPath, string $dest): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return;
        }
        if (! is_dir($dest)) {
            mkdir($dest, 0775, true);
        }
        $root = realpath($dest);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || in_array('..', explode('/', $name), true)) {
                continue;
            }
            $target = $dest.'/'.$name;
            if (str_ends_with($name, '/')) {
                if (! is_dir($target)) {
                    mkdir($target, 0775, true);
                }

                continue;
            }
            $parent = dirname($target);
            if (! is_dir($parent)) {
                mkdir($parent, 0775, true);
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }
            file_put_contents($target, $contents);
            $written = realpath($target);
            if ($root === false || $written === false || ! str_starts_with($written, $root.DIRECTORY_SEPARATOR)) {
                if (is_file($target)) {
                    unlink($target);
                }
            }
        }
        $zip->close();
    }

    private function removeDirectory(string $directory): void
    {
        $library = $this->libraryRoot();
        $real = realpath($directory);
        $base = $library !== null ? realpath($library) : false;
        if ($real === false || $base === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($directory);
    }

    private function skipped(string $directory, string $path): bool
    {
        $relative = substr(str_replace('\\', '/', $path), strlen(rtrim(str_replace('\\', '/', $directory), '/')) + 1);
        foreach (explode('/', $relative) as $part) {
            if (in_array($part, ['.git', 'node_modules', 'vendor', 'storage'], true)) {
                return true;
            }
        }

        return false;
    }
}
