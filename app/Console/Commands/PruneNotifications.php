<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes read notifications older than 90 days and any older than a year.
 */
#[Signature('notifications:prune')]
#[Description('Delete old notifications')]
class PruneNotifications extends Command
{
    public const READ_DAYS = 90;

    public const MAX_DAYS = 365;

    public function handle(): int
    {
        $deleted = DB::table('notifications')
            ->where(fn ($q) => $q
                ->where(fn ($read) => $read->whereNotNull('read_at')->where('read_at', '<', now()->subDays(self::READ_DAYS)))
                ->orWhere('created_at', '<', now()->subDays(self::MAX_DAYS)))
            ->delete();

        // Event keys are kept as long as any notification can be.
        DB::table('notification_dedupes')->where('created_at', '<', now()->subDays(self::MAX_DAYS))->delete();

        $this->info("Deleted {$deleted} old notifications.");

        return self::SUCCESS;
    }
}
