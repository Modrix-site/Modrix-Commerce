<?php

namespace Tests;

use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use App\Core\Authorization\PermissionSynchronizer;
use App\Core\Authorization\SystemRoles;
use App\Core\Installation\InstallationState;
use App\Models\User;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    private ?string $installLockFile = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests share storage/ with the dev server: never create the real install lock.
        $this->installLockFile = sys_get_temp_dir().'/ecomodular-installed-'.bin2hex(random_bytes(6));
        config(['ecomodular.installer.lock_file' => $this->installLockFile]);

        // MySQL commits implicitly on DDL, which ends the transaction that
        // RefreshDatabase wraps each test in (writes after it are committed,
        // as they always were). When a test installs a module (CREATE TABLE),
        // tell the connection there is no open transaction any more, so code
        // under test uses real transactions instead of a savepoint that no
        // longer exists ("SAVEPOINT trans2 does not exist").
        $this->app['events']->listen(MigrationsEnded::class, function (): void {
            $connection = DB::connection();

            if ($connection->getDriverName() === 'mysql' && $connection->transactionLevel() > 0 && ! $connection->getPdo()->inTransaction()) {
                (fn () => $this->transactions = 0)->call($connection);
                // What the test writes from here on is committed, so the next
                // test starts from a freshly migrated database.
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    protected function tearDown(): void
    {
        if ($this->installLockFile !== null && is_file($this->installLockFile)) {
            @unlink($this->installLockFile);
        }

        parent::tearDown();
    }

    /** For tests built on SQLite internals (file dumps, VACUUM, the "main" schema, non-transactional DDL). */
    protected function onlySqlite(string $why): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite only: '.$why);
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Sync permissions/system roles and mark the platform installed,
     * as `ecomodular:install` would.
     */
    protected function installPlatform(): void
    {
        app(PermissionSynchronizer::class)->sync();
        app(InstallationState::class)->markInstalled();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail()->id, ['created_at' => now()]);

        return $user->fresh();
    }

    protected function owner(array $attributes = []): User
    {
        return $this->userWithRole(SystemRoles::OWNER, $attributes);
    }

    /**
     * Creates a custom role with exactly the given permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function roleWithPermissions(string $name, array $permissions): Role
    {
        $role = new Role(['name' => $name, 'display_name' => ucfirst($name)]);
        $role->name = $name;
        $role->save();
        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissions)->pluck('id')
        );

        return $role;
    }

    /**
     * Act as the user with a fresh password confirmation (for password.confirm routes).
     */
    protected function actingAsConfirmed(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }
}
