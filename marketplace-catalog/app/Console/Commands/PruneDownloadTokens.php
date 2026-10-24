<?php

namespace Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Marketplace\Models\DownloadToken;

/** Removes download links that expired more than a day ago. */
class PruneDownloadTokens extends Command
{
    protected $signature = 'marketplace:prune-downloads';

    protected $description = 'Delete expired one-time download links';

    public function handle(): int
    {
        $deleted = DownloadToken::query()->where('expires_at', '<', now()->subDay())->delete();
        $this->info("Deleted {$deleted} expired download links.");

        return self::SUCCESS;
    }
}
