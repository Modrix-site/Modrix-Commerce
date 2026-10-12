<?php

namespace Database\Seeders;

use App\Core\Authorization\Models\Role;
use App\Core\Authorization\PermissionSynchronizer;
use App\Core\Installation\InstallationState;
use App\Core\Settings\StoreSettings;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT/DEMO DATA ONLY. Creates one staff account per system role
 * (password: "password") and marks the platform installed.
 * Refuses to run in production.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(PermissionSynchronizer $permissions, InstallationState $installation, StoreSettings $store): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentSeeder must never run in production.');
        }

        $permissions->sync();

        foreach (Role::query()->where('is_system', true)->get() as $role) {
            $user = User::query()->firstOrCreate(
                ['email' => "{$role->name}@example.test"],
                [
                    'name' => "Demo {$role->display_name}",
                    'password' => 'password',
                ],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->roles()->syncWithoutDetaching([$role->id => ['created_at' => now()]]);
        }

        User::query()->firstOrCreate(
            ['email' => 'customer@example.test'],
            ['name' => 'Demo Customer', 'password' => 'password'],
        )->forceFill(['email_verified_at' => now()])->save();

        if (! $installation->isInstalled()) {
            $store->update([
                'store_name' => 'EcoModular Demo Store',
                'store_email' => 'owner@example.test',
                'default_currency' => 'USD',
                'timezone' => 'UTC',
            ]);
            $installation->markInstalled();
        }
    }
}
