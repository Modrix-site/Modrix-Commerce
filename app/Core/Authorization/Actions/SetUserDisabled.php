<?php

namespace App\Core\Authorization\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Authorization\EscalationGuard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SetUserDisabled
{
    public function __construct(
        private readonly EscalationGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, User $target, bool $disabled): void
    {
        $this->guard->ensureNotSelf($actor, $target, 'user', 'You cannot disable or enable your own account.');
        $this->guard->ensureCanManageAccount($actor, $target);

        DB::transaction(function () use ($actor, $target, $disabled) {
            if ($disabled === $target->isDisabled()) {
                return;
            }

            if ($disabled) {
                $this->guard->ensureNotLastOwner($target, 'user', 'The last active owner cannot be disabled.');
            }

            $target->forceFill([
                'disabled_at' => $disabled ? now() : null,
                'remember_token' => null,
            ])->save();

            if ($disabled && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $target->getKey())->delete();
            }

            $this->audit->record($disabled ? 'user.disabled' : 'user.enabled', $target, [], $actor);
        });
    }
}
