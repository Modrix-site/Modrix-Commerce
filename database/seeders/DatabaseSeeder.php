<?php

namespace Database\Seeders;

use App\Core\Authorization\PermissionSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Production-safe seeding: only platform reference data (permissions and
 * system roles). Demo data lives in DevelopmentSeeder and must be requested
 * explicitly: php artisan db:seed --class=DevelopmentSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(PermissionSynchronizer $permissions): void
    {
        $permissions->sync();
    }
}
