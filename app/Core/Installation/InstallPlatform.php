<?php

namespace App\Core\Installation;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\Models\Role;
use App\Core\Authorization\PermissionSynchronizer;
use App\Core\Authorization\SystemRoles;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ThemeManager;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Performs first-time setup: syncs permissions and system roles, creates the
 * owner account, stores initial store settings and locks the installer.
 */
final class InstallPlatform
{
    public function __construct(
        private readonly InstallationState $state,
        private readonly PermissionSynchronizer $permissions,
        private readonly StoreSettings $storeSettings,
        private readonly InstallerToken $token,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, store_name: string, default_currency: string, timezone: string}  $data
     */
    public function handle(array $data): User
    {
        // Serialise concurrent install attempts; the second one sees isInstalled() === true.
        $lock = Cache::lock('ecomodular:install', 30);

        if (! $lock->get()) {
            throw new RuntimeException('Another installation is in progress.');
        }

        try {
            if ($this->state->isInstalled()) {
                throw new RuntimeException('EcoModular is already installed.');
            }

            $owner = DB::transaction(function () use ($data) {
                $this->permissions->sync();

                $owner = new User;
                $owner->forceFill([
                    'name' => $data['name'],
                    'email' => Str::lower($data['email']),
                    'password' => $data['password'],
                    // The installing operator has server access; the address is trusted.
                    'email_verified_at' => now(),
                ])->save();

                $ownerRole = Role::query()->where('name', SystemRoles::OWNER)->firstOrFail();
                $owner->roles()->attach($ownerRole->id, ['created_at' => now()]);

                $this->storeSettings->update([
                    'store_name' => $data['store_name'],
                    'store_email' => Str::lower($data['email']),
                    'default_currency' => $data['default_currency'],
                    'timezone' => $data['timezone'],
                ]);

                $this->state->markInstalled();

                $this->audit->record('system.installed', $owner, [
                    'owner_email' => $owner->email,
                    'store_name' => $data['store_name'],
                ], $owner);

                return $owner;
            });

            $this->token->delete();
            $this->activateDefaultTheme($owner);

            return $owner;
        } finally {
            $lock->release();
        }
    }

    /**
     * The bundled default theme becomes the active theme. Optional: without
     * it the storefront still renders with the default or built-in design.
     */
    private function activateDefaultTheme(User $owner): void
    {
        try {
            app(ThemeManager::class)->activate((string) config('ecomodular.themes.default', 'ecomodular/essence'), $owner);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
