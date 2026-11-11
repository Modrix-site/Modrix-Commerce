<?php

namespace EcoModular\ExternalStorage;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Media\Media;
use App\Core\Media\ProductImageUrls;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Models\User;
use EcoModular\ExternalStorage\Console\SyncExternalStorageCommand;
use EcoModular\ExternalStorage\Filesystem\ExternalStorageFlysystemAdapter;
use EcoModular\ExternalStorage\Models\ExternalStorageFile;
use EcoModular\ExternalStorage\Services\ExternalStorageClient;
use EcoModular\ExternalStorage\Services\MediaOffloadService;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Throwable;

class ExternalStorageServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/external-storage';

    public const ADMIN_ROUTE_PREFIX = 'admin.modrixcommerce.external-storage.';

    public function register(): void
    {
        $this->app->scoped(ExternalStorageSettings::class);
        $this->app->scoped(ExternalStorageClient::class);
        $this->app->scoped(MediaOffloadService::class);
    }

    public function boot(StorefrontSlots $slots): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SyncExternalStorageCommand::class]);
        }

        $this->registerFilesystemDisk();
        $this->configureAutomaticExternalDisk();
        $this->registerImageUrlResolver();
        $this->registerModelListeners();
        $this->registerSlots($slots);
    }

    /**
     * Registers the `external_storage` disk driver in Laravel's Filesystem manager.
     */
    private function registerFilesystemDisk(): void
    {
        Storage::extend('external_storage', function ($app, array $config) {
            $client = $app->make(ExternalStorageClient::class);
            $adapter = new ExternalStorageFlysystemAdapter($client);
            $flysystem = new Filesystem($adapter, $config, publicUrlGenerator: $adapter);

            return new FilesystemAdapter($flysystem, $adapter, $config);
        });

        config([
            'filesystems.disks.external_storage' => [
                'driver' => 'external_storage',
                'visibility' => 'public',
            ],
        ]);
    }

    /**
     * Switches `ecomodular.media_disk` to `external_storage` when configured to save
     * automatically in external storage instead of locally, and registers CSP image origins.
     */
    public function configureAutomaticExternalDisk(): void
    {
        try {
            $settings = $this->app->make(ExternalStorageSettings::class);
            $client = $this->app->make(ExternalStorageClient::class);

            if (! $settings->enabled()) {
                return;
            }

            $basePublicUrl = $settings->cdnUrl() !== ''
                ? $settings->cdnUrl()
                : $client->resolveBaseEndpoint();

            config([
                'filesystems.disks.external_storage.url' => $basePublicUrl,
            ]);

            $existingSources = (array) config('ecomodular.security.img_sources', []);
            if ($basePublicUrl !== '' && ! in_array($basePublicUrl, $existingSources, true)) {
                $existingSources[] = $basePublicUrl;
                config(['ecomodular.security.img_sources' => $existingSources]);
            }

            if ($settings->shouldSaveAutomaticallyInExternal()
                && $settings->saveMode() === 'external_only'
                && $settings->autoOffloadPhotos()
                && $settings->isConfigured()
            ) {
                config(['ecomodular.media_disk' => 'external_storage']);
            }
        } catch (Throwable) {
            // Safe fallback during initial install or before settings table is ready.
        }
    }

    /**
     * Hooks into core `ProductImageUrls` (`catalog.image_urls`) so product images stored
     * in external cloud storage resolve to their Wasabi / S3 / Cloudflare / CDN URL.
     */
    private function registerImageUrlResolver(): void
    {
        $this->app->make(ProductImageUrls::class)->register(self::MODULE, function (ProductImage $image): ?string {
            try {
                return $this->app->make(MediaOffloadService::class)->resolveProductImageUrl($image);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });
    }

    /**
     * Hooks into ProductImage and Media lifecycle events to automatically save new
     * uploads to external storage and clean up deleted files.
     */
    private function registerModelListeners(): void
    {
        ProductImage::created(function (ProductImage $image): void {
            try {
                $settings = $this->app->make(ExternalStorageSettings::class);
                if ($settings->shouldSaveAutomaticallyInExternal() && $settings->autoOffloadPhotos()) {
                    $this->app->make(MediaOffloadService::class)->offloadProductImage($image);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        ProductImage::deleting(function (ProductImage $image): void {
            try {
                $record = ExternalStorageFile::query()->where('product_image_id', $image->getKey())->first();
                if ($record instanceof ExternalStorageFile) {
                    $this->app->make(MediaOffloadService::class)->deleteExternalFile($record, false);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        Media::created(function (Media $media): void {
            try {
                $settings = $this->app->make(ExternalStorageSettings::class);
                if (! $settings->shouldSaveAutomaticallyInExternal()) {
                    return;
                }

                $isVideo = str_starts_with($media->mime, 'video/');
                if (($isVideo && $settings->autoOffloadVideos()) || (! $isVideo && $settings->autoOffloadPhotos())) {
                    $this->app->make(MediaOffloadService::class)->offloadMedia($media);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        Media::deleting(function (Media $media): void {
            try {
                $record = ExternalStorageFile::query()->where('media_id', $media->getKey())->first();
                if ($record instanceof ExternalStorageFile) {
                    $this->app->make(MediaOffloadService::class)->deleteExternalFile($record, false);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Registers Storefront product video gallery slot and Admin product editor sidebar slot.
     */
    private function registerSlots(StorefrontSlots $slots): void
    {
        // Storefront slot: Product Videos & Cloud Media Gallery on product page
        $slots->register('product.after_details', self::MODULE, 'ProductExternalMediaSlot', function (array $context) {
            $product = $context['product'] ?? null;
            if (! $product instanceof Product) {
                return null;
            }

            $settings = $this->app->make(ExternalStorageSettings::class);
            if (! $settings->enabled() || ! $settings->showProductVideos()) {
                return null;
            }

            $videos = ExternalStorageFile::query()
                ->where('product_id', $product->id)
                ->where('media_type', ExternalStorageFile::TYPE_VIDEO)
                ->where('status', ExternalStorageFile::STATUS_SYNCED)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (ExternalStorageFile $f) => [
                    'id' => $f->id,
                    'title' => $f->title ?: $f->filename,
                    'url' => $f->publicUrl(),
                    'mime_type' => $f->mime_type,
                    'provider_label' => $settings->providerLabel($f->provider),
                ])
                ->values()
                ->all();

            if ($videos === []) {
                return null;
            }

            return [
                'productName' => $product->localized('name') ?: $product->name,
                'videos' => $videos,
            ];
        }, 45);

        // Admin Product Editor Sidebar slot: Manage product photos & videos in external cloud storage
        $slots->register('admin.product.sidebar', self::MODULE, 'AdminProductStoragePanel', function (array $context) {
            $product = $context['product'] ?? null;
            $user = auth()->user();

            if (! $product instanceof Product || ! $user instanceof User || ! $user->can('external_storage.manage')) {
                return null;
            }

            $settings = $this->app->make(ExternalStorageSettings::class);
            $files = ExternalStorageFile::query()
                ->where('product_id', $product->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn (ExternalStorageFile $f) => [
                    'id' => $f->id,
                    'media_type' => $f->media_type,
                    'title' => $f->title ?: $f->filename,
                    'url' => $f->publicUrl(),
                    'provider_label' => $settings->providerLabel($f->provider),
                    'local_deleted' => $f->local_deleted,
                    'status' => $f->status,
                ])
                ->values()
                ->all();

            return [
                'productId' => $product->id,
                'enabled' => $settings->enabled(),
                'isConfigured' => $settings->isConfigured(),
                'providerLabel' => $settings->providerLabel(),
                'saveMode' => $settings->saveMode(),
                'files' => $files,
                'uploadUrl' => route(self::ADMIN_ROUTE_PREFIX.'upload'),
                'syncProductUrl' => route(self::ADMIN_ROUTE_PREFIX.'products.sync', $product->id),
                'dashboardUrl' => route(self::ADMIN_ROUTE_PREFIX.'index'),
            ];
        });
    }
}
