<?php

namespace App\Core\Authorization\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\EscalationGuard;
use App\Core\Authorization\Exceptions\AuthorizationRuleViolation;
use App\Core\Authorization\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteRole
{
    public function __construct(
        private readonly EscalationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Role $role): void
    {
        if ($role->is_system) {
            throw AuthorizationRuleViolation::make('role', 'System roles cannot be deleted.');
        }

        $this->guard->ensureCanEditRole($actor, $role, []);

        DB::transaction(function () use ($actor, $role) {
            if ($role->users()->exists()) {
                throw AuthorizationRuleViolation::make('role', 'Remove this role from all users before deleting it.');
            }

            $this->audit->record('role.deleted', $role, [
                'name' => $role->name,
                'permissions' => $role->load('permissions')->permissionNames(),
            ], $actor);

            $role->delete();
        });
    }
}
