<?php

namespace EcoModular\BotProtection\Console;

use EcoModular\BotProtection\Models\BotBlockedIp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CleanExpiredBlocksCommand extends Command
{
    protected $signature = 'bot:clean-expired';

    protected $description = 'Remove expired temporary IP blocks';

    public function handle(): int
    {
        $count = BotBlockedIp::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        if ($count > 0) {
            Cache::forget('bot_blocked_ips_list');
            $this->info("Cleaned up {$count} expired IP blocks.");
        } else {
            $this->info('No expired IP blocks to clean.');
        }

        return self::SUCCESS;
    }
}
