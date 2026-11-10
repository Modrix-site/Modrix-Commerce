<?php

namespace Tests\Feature\Marketplace;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Payments\PaymentMethods;
use EcoModular\CloudConnector\StoreLicenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class CCAvenuePackageTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    public function test_catalog_license_pulls_signed_archive_into_core_and_installs_module(): void
    {
        $this->installPlatform();
        $this->setUpModules();
        $this->enableBundledModule('modrixcommerce/cloud-connector');
        $catalogStorage = storage_path('framework/testing/ccavenue-catalog-'.Str::random(10));
        $oldStorage = getenv('CATALOG_STORAGE');
        putenv('CATALOG_STORAGE='.$catalogStorage);
        $this->beforeApplicationDestroyed(function () use ($oldStorage, $catalogStorage) {
            putenv($oldStorage === false ? 'CATALOG_STORAGE' : 'CATALOG_STORAGE='.$oldStorage);
            File::deleteDirectory($catalogStorage);
        });
        require_once base_path('marketplace-catalog/src/RepositoryAdmin.php');
        require_once base_path('marketplace-catalog/src/Catalog.php');
        require_once base_path('marketplace-catalog/src/Store.php');
        require_once base_path('marketplace-catalog/src/Licenses.php');
        $catalog = new \Catalog(base_path());
        $store = new \Store($catalogStorage);
        $licenses = new \Licenses($store);
        $product = collect($store->repository($catalog->products(), true))->firstWhere('extension', 'modrixcommerce/ccavenue');
        $this->assertSame('Modrix team', $product['author']);
        $store->db->prepare('INSERT INTO customers (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)')
            ->execute(['Buyer', 'ccavenue@example.test', password_hash('test-password', PASSWORD_DEFAULT), date('c')]);
        $key = $licenses->generate((int) $store->db->lastInsertId(), $product)['license_key'];
        $licenses->publishPackage($product);
        config(['ecomodular.marketplace.url' => 'https://catalog.example.test', 'ecomodular.marketplace.trusted_keys' => [$licenses->publicKey()]]);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($licenses, $store, $catalog, $product) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/api/marketplace/v1/catalog') {
                return Http::response($catalog->api('https://catalog.example.test', [$product], 'USD'));
            }
            if (str_starts_with($path, '/marketplace/download/')) {
                $file = (new \PackageRelease($store))->redeem(basename($path));

                return Http::response(file_get_contents($file['path']));
            }

            return Http::response($licenses->api(substr($path, strlen('/api/marketplace/v1/')), $request->data(), 'https://catalog.example.test'));
        });
        // No package exists in this store before the license is connected.
        $this->assertFileDoesNotExist($this->modulesPath.'/modrixcommerce/ccavenue/module.json');
        if (PHP_OS_FAMILY === 'Windows') {
            $filesystem = File::getFacadeRoot();
            $firstMove = true;
            File::partialMock()->shouldReceive('moveDirectory')->andReturnUsing(function ($from, $to) use ($filesystem, &$firstMove) {
                if ($firstMove) {
                    $firstMove = false;

                    return false;
                }

                return $filesystem->moveDirectory($from, $to);
            });
        }
        $license = app(StoreLicenses::class)->activate($key, $this->userWithRole('administrator'));
        $this->assertNull($license->last_error);
        $this->assertSame('active', $license->status);
        $this->assertFileExists($this->modulesPath.'/modrixcommerce/ccavenue/module.json');
        $extension = Extension::query()->where('name', 'modrixcommerce/ccavenue')->firstOrFail();
        $this->assertSame(ModuleState::Discovered, $extension->state);
        app(ModuleManager::class)->install('modrixcommerce/ccavenue');
        app(ModuleManager::class)->enable('modrixcommerce/ccavenue', true);
        $this->assertNotNull(app(PaymentMethods::class)->gatewayForProvider('ccavenue'));
    }
}
