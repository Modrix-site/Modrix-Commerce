<?php

namespace App\Console\Commands;

use App\Core\Authorization\PermissionSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ecomodular:sync-permissions')]
#[Description('Mirror registered permissions into the database and create missing system roles')]
class SyncPermissionsCommand extends Command
{
    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $result = $synchronizer->sync();

        $this->components->info(sprintf(
            'Permissions synced (%d new). System roles ensured (%d new).',
            $result['permissions_created'],
            $result['roles_created'],
        ));

        return self::SUCCESS;
    }
}
