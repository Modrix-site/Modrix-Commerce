<?php

namespace App\Http\Controllers\Admin;

use App\Core\Authorization\Actions\DeleteRole;
use App\Core\Authorization\Actions\SaveRole;
use App\Core\Authorization\Models\Permission;
use App\Core\Authorization\Models\Role;
use App\Core\Authorization\PermissionRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $this->actor($request);

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->isOwner() ? null : $role->permissions->count(),
                'editable' => $this->canEdit($actor, $role),
            ]);

        return Inertia::render('admin/roles/index', ['roles' => $roles]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => null,
            'permissionGroups' => $this->permissionGroups($this->actor($request)),
        ]);
    }

    public function store(Request $request, SaveRole $save): RedirectResponse
    {
        $role = $save->handle($this->actor($request), null, $this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return redirect()->route('admin.roles.edit', $role);
    }

    public function edit(Request $request, Role $role): Response
    {
        $actor = $this->actor($request);
        abort_if($role->isOwner(), 404);

        return Inertia::render('admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->load('permissions')->permissionNames(),
                'users_count' => $role->users()->count(),
                'editable' => $this->canEdit($actor, $role),
            ],
            'permissionGroups' => $this->permissionGroups($actor),
        ]);
    }

    public function update(Request $request, Role $role, SaveRole $save): RedirectResponse
    {
        $save->handle($this->actor($request), $role, $this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return redirect()->route('admin.roles.edit', $role);
    }

    public function destroy(Request $request, Role $role, DeleteRole $delete): RedirectResponse
    {
        $delete->handle($this->actor($request), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return redirect()->route('admin.roles.index');
    }

    /**
     * @return array{display_name: string, description: string|null, permissions: list<string>}
     */
    private function validated(Request $request): array
    {
        $registered = app(PermissionRegistry::class)->names();

        // No checked permission is submitted as a missing key, meaning "no permissions".
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($registered), Rule::exists('permissions', 'name')],
        ]);

        return [
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'permissions' => array_values($data['permissions'] ?? []),
        ];
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    private function canEdit(User $actor, Role $role): bool
    {
        if ($role->isOwner()) {
            return false;
        }

        return $actor->isOwner() || array_diff($role->permissionNames(), $actor->permissionNames()) === [];
    }

    /**
     * @return list<array{group: string, permissions: list<array{name: string, label: string, grantable: bool}>}>
     */
    private function permissionGroups(User $actor): array
    {
        $held = $actor->permissionNames();
        $registry = app(PermissionRegistry::class);

        /** @var list<array{group: string, permissions: list<array{name: string, label: string, grantable: bool}>}> */
        return Permission::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Permission $p) => $registry->has($p->name))
            ->groupBy('group')
            ->map(fn ($permissions, string $group) => [
                'group' => $group,
                'permissions' => $permissions->map(fn (Permission $p) => [
                    'name' => $p->name,
                    'label' => $p->display_name,
                    'grantable' => $actor->isOwner() || in_array($p->name, $held, true),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
