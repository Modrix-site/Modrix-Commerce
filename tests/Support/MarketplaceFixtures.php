<?php

namespace Tests\Support;

use App\Core\Marketplace\PackageArchive;
use App\Core\Marketplace\Signing;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Marketplace\ReleasePublisher;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs the marketplace server and a merchant store in the same test app.
 * Client HTTP calls to the marketplace URL are dispatched into the app, so
 * the real API, signatures and downloads are exercised end to end.
 */
trait MarketplaceFixtures
{
    protected string $publicKey;

    protected string $packagesPath;

    protected function setUpMarketplace(): void
    {
        $keys = Signing::generateKeyPair();
        $this->publicKey = $keys['public'];

        config([
            'ecomodular.marketplace.server.enabled' => true,
            'ecomodular.marketplace.server.signing_key' => $keys['secret'],
            'ecomodular.marketplace.server.stripe_secret' => 'sk_test_marketplace0000000000',
            'ecomodular.marketplace.server.stripe_webhook_secret' => 'whsec_marketplace0000000000',
            'ecomodular.marketplace.trusted_keys' => [$keys['public']],
            'ecomodular.marketplace.url' => rtrim((string) config('app.url'), '/'),
        ]);

        Storage::fake('local');
        $this->packagesPath = storage_path('framework/testing/packages-'.Str::random(8));
        File::ensureDirectoryExists($this->packagesPath);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->packagesPath));
    }

    /**
     * Routes the store's calls to the marketplace into this application.
     *
     * @param  array<string, mixed>  $extra  additional fakes (e.g. Stripe)
     */
    protected function routeMarketplaceToApp(array $extra = []): void
    {
        $base = rtrim((string) config('ecomodular.marketplace.url'), '/');

        Http::fake([
            ...$extra,
            $base.'/*' => function (ClientRequest $request) {
                $parts = parse_url($request->url());
                $uri = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
                $response = $this->call($request->method(), $uri, [], [], [], [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_ACCEPT' => 'application/json',
                    'REMOTE_ADDR' => '203.0.113.7',
                ], $request->body());

                $body = $response->baseResponse instanceof StreamedResponse ? $response->streamedContent() : (string) $response->getContent();

                return Http::response($body, $response->getStatusCode(), ['Content-Type' => (string) $response->headers->get('Content-Type')]);
            },
        ]);
    }

    /**
     * Builds a module package zip from a ModuleFixtures-style directory.
     */
    protected function packageModule(string $version = '1.0.0', bool $withSecondMigration = false): string
    {
        // Build the package in its own directory, then put the store's modules path back.
        $storeModules = $this->modulesPath;
        $this->modulesPath = $this->packagesPath.'/src-'.$version;
        $dir = $this->makeHelloModule($version, withSecondMigration: $withSecondMigration);
        $this->modulesPath = $storeModules;

        $zip = $this->packagesPath."/hello-{$version}.zip";
        PackageArchive::create($dir, $zip);

        return $zip;
    }

    protected function product(array $attributes = []): MarketplaceProduct
    {
        $product = new MarketplaceProduct;
        $product->forceFill(array_merge([
            'publisher_id' => Publisher::platform()->id,
            'slug' => 'hello-pro',
            'extension_name' => 'acme/hello',
            'type' => 'module',
            'name' => 'Hello Pro',
            'summary' => 'Says hello.',
            'price' => '49.00',
            'currency' => 'USD',
            'status' => MarketplaceProduct::PUBLISHED,
            'updates_months' => 12,
            'activation_limit' => 2,
        ], $attributes))->save();

        return $product;
    }

    protected function publish(MarketplaceProduct $product, string $zip, ?User $owner = null): MarketplaceRelease
    {
        $owner ??= $this->owner();
        $publisher = app(ReleasePublisher::class);
        $release = $publisher->upload($product, new UploadedFile($zip, basename($zip), 'application/zip', null, true), 'Changes.', $owner);

        return $publisher->review($release, true, 'Looks good.', $owner);
    }
}
