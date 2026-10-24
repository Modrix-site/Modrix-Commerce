<?php

/** Publish a repository package using the catalog's existing signer and license API. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__).'/src/env.php';
catalog_load_env(dirname(__DIR__).'/.env');
require dirname(__DIR__).'/src/RepositoryAdmin.php';
require dirname(__DIR__).'/src/Catalog.php';
require dirname(__DIR__).'/src/Store.php';
require dirname(__DIR__).'/src/Licenses.php';

$name = $argv[1] ?? '';
if (! preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#D', $name)) {
    fwrite(STDERR, "Usage: php marketplace-catalog/bin/publish-package.php vendor/package\n");
    exit(1);
}

$root = getenv('CATALOG_ROOT') ?: dirname(__DIR__, 2);
$catalog = new Catalog($root);
$store = new Store(catalogStorage());
$licenses = new Licenses($store);
$source = $catalog->find(str_replace('/', '-', $name));
if ($source === null) {
    throw new RuntimeException('Package not found in the catalog source tree.');
}
$products = $store->repository([$source]);
$product = array_values(array_filter($products, fn ($p) => $p['extension'] === $name))[0];
$product = array_replace($product, $source);
$metadataFile = dirname(__DIR__).'/listings/'.$product['slug'].'.json';
if (is_file($metadataFile)) {
    $metadata = json_decode(file_get_contents($metadataFile), true, flags: JSON_THROW_ON_ERROR);
    $product = array_replace($product, array_intersect_key($metadata, array_flip(['categories', 'features'])));
}
$product['status'] = 'published';
// Use repository source when publishing, never a stale previously uploaded ZIP.
unset($product['package']);
$licenses->publishPackage($product);
$query = $store->db->prepare('SELECT path, sha256 FROM package_releases WHERE extension = ? AND version = ?');
$query->execute([$name, $product['version']]);
$release = $query->fetch(PDO::FETCH_ASSOC);
if (! is_array($release) || ! is_file($release['path'])) {
    throw new RuntimeException('The signed package release was not created.');
}
$filename = substr($release['sha256'], 0, 32).'.zip';
$packageDirectory = catalogStorage().'/packages';
if (! is_dir($packageDirectory)) {
    mkdir($packageDirectory, 0775, true);
}
if (! copy($release['path'], $packageDirectory.'/'.$filename)) {
    throw new RuntimeException('Unable to save the catalog download.');
}
$product['package'] = $filename;
$store->saveListing($product);
echo json_encode([
    'extension' => $name, 'version' => $product['version'], 'publisher' => $product['author'],
    'item_path' => '/item/'.$product['slug'], 'archive' => $release['path'],
    'sha256' => $release['sha256'], 'license_download_ready' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
