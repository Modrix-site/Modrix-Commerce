<?php

namespace EcoModular\ImageStudio\Console;

use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Jobs\ProcessProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageStudio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ProcessImagesCommand extends Command
{
    protected $signature = 'image-studio:process {--all : Process every product image without a finished variant} {--product=* : Limit to these product ids} {--force : Also reprocess images that already have a done variant} {--sync : Process inline instead of queueing}';

    protected $description = 'Generate Image Studio variants for existing product images.';

    public function handle(ImageStudio $studio): int
    {
        $products = array_values(array_filter(
            array_map('intval', (array) $this->option('product')),
            fn (int $id): bool => $id > 0,
        ));

        if (! $this->option('all') && $products === []) {
            $this->error('Choose what to process: --all or --product=ID.');
            $this->line('Usage: php artisan image-studio:process {--all} {--product=*} {--force} {--sync}');

            return self::FAILURE;
        }

        $query = ProductImage::query()->orderBy('id');
        if ($products !== []) {
            $query->whereIn('product_id', $products);
        }
        if (! $this->option('force')) {
            $query->whereNotIn('id', ImageVariant::query()->where('status', ImageVariant::DONE)->select('product_image_id'));
        }

        return $this->option('sync') ? $this->runSync($query, $studio) : $this->runQueued($query, $studio);
    }

    /**
     * @param  Builder<ProductImage>  $query
     */
    private function runQueued(Builder $query, ImageStudio $studio): int
    {
        $count = 0;
        $query->chunkById(200, function ($images) use ($studio, &$count): void {
            $count += $studio->queueMany($images);
        });

        $this->info("Queued {$count} images.");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<ProductImage>  $query
     */
    private function runSync(Builder $query, ImageStudio $studio): int
    {
        $counts = [ImageVariant::DONE => 0, ImageVariant::SKIPPED => 0, ImageVariant::FAILED => 0];
        $bar = $this->output->createProgressBar((clone $query)->count());
        $bar->start();

        $query->chunkById(50, function ($images) use ($studio, $bar, &$counts): void {
            foreach ($images as $image) {
                $studio->markPending($image);

                try {
                    ProcessProductImage::dispatchSync($image->id);
                } catch (Throwable) {
                    // The job already recorded the failure on the variant row.
                }

                $status = ImageVariant::query()->where('product_image_id', $image->id)->value('status');
                $key = in_array($status, [ImageVariant::DONE, ImageVariant::SKIPPED], true) ? $status : ImageVariant::FAILED;
                $counts[$key]++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Processed: {$counts[ImageVariant::DONE]} done, {$counts[ImageVariant::SKIPPED]} skipped, {$counts[ImageVariant::FAILED]} failed.");

        return self::SUCCESS;
    }
}
