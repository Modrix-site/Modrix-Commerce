<?php

namespace EcoModular\ImageStudio;

use App\Core\Media\ProductImageUrls;
use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Console\ProcessImagesCommand;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageStudio;
use EcoModular\ImageStudio\Services\VariantUrls;
use EcoModular\ImageStudio\Support\ImageStudioSettings;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ImageStudioServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/image-studio';

    public function register(): void
    {
        // Scoped: a queue worker forgets scoped instances between jobs, so setting and
        // remove.bg key changes made in the admin reach the next job without a restart.
        $this->app->scoped(ImageStudioSettings::class);
        // Scoped so queue workers and tests never see a stale variant map.
        $this->app->scoped(VariantUrls::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ProcessImagesCommand::class]);
        }

        // The setting is checked on every call so toggling it needs no reboot.
        // A failing lookup (e.g. the table is missing mid-migration) must never
        // break the storefront: report it and fall back to the original.
        $this->app->make(ProductImageUrls::class)->register(self::MODULE, function (ProductImage $image): ?string {
            try {
                if (! app(ImageStudioSettings::class)->enabled()) {
                    return null;
                }

                return app(VariantUrls::class)->resolve($image);
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        });

        ProductImage::created(function (ProductImage $image): void {
            $settings = app(ImageStudioSettings::class);
            if ($settings->enabled() && $settings->processOnUpload()) {
                app(ImageStudio::class)->queue($image);
            }
        });

        ProductImage::deleting(function (ProductImage $image): void {
            try {
                $variant = ImageVariant::query()->where('product_image_id', $image->getKey())->first();
                if ($variant instanceof ImageVariant) {
                    app(ImageStudio::class)->deleteFile($variant->disk, $variant->path);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
