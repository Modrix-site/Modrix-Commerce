<?php

namespace App\Core\Authorization\Models;

use App\Core\Authorization\SystemRoles;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'display_name', 'description'])]
class Role extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('created_at');
    }

    public function isOwner(): bool
    {
        return $this->name === SystemRoles::OWNER;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        /** @var list<string> */
        return $this->permissions->pluck('name')->values()->all();
    }
}
