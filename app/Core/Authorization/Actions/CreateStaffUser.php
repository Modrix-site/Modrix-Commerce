<?php

namespace App\Core\Authorization\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\EscalationGuard;
use App\Core\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates a staff account with an unusable random password and emails the
 * person a password-reset link, so no administrator ever knows or transmits
 * another user's password.
 */
final class CreateStaffUser
{
    public function __construct(
        private readonly EscalationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, roles: list<int>}  $data
     */
    public function handle(User $actor, array $data): User
    {
        $roles = Role::query()->with('permissions')->whereKey($data['roles'])->get();
        $this->guard->ensureCanGrantRoles($actor, $roles);

        $user = DB::transaction(function () use ($actor, $data, $roles) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Str::password(64),
            ]);

            $user->roles()->sync(
                $roles->mapWithKeys(fn (Role $role) => [$role->id => ['created_at' => now()]])->all()
            );

            $this->audit->record('user.created', $user, [
                'email' => $user->email,
                'roles' => $roles->pluck('name')->values()->all(),
            ], $actor);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $user->email]);

        return $user;
    }
}
