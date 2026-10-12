<?php

namespace App\Http\Controllers\Admin;

use App\Core\Authorization\Actions\CreateStaffUser;
use App\Core\Authorization\Actions\SetUserDisabled;
use App\Core\Authorization\Actions\SyncUserRoles;
use App\Core\Authorization\Models\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);

        $users = User::query()
            ->whereHas('roles')
            ->with('roles:id,name,display_name')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%'));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('disabled_at'))
            ->when(($filters['status'] ?? null) === 'disabled', fn ($q) => $q->whereNotNull('disabled_at'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'canManage' => $request->user()?->can('users.manage') ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/create', [
            'roles' => $this->assignableRoles($this->actor($request)),
        ]);
    }

    public function store(Request $request, CreateStaffUser $create): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        /** @var array{name: string, email: string, roles: list<int>} $data */
        $user = $create->handle($this->actor($request), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff user created. A password setup link was emailed to :email.', ['email' => $user->email])]);

        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(Request $request, User $user): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('admin/users/edit', [
            'user' => $this->present($user->load('roles:id,name,display_name')),
            'roles' => $this->assignableRoles($actor),
            'isSelf' => $actor->is($user),
        ]);
    }

    public function updateRoles(Request $request, User $user, SyncUserRoles $sync): RedirectResponse
    {
        // An empty selection is submitted as a missing key, which means "remove all roles".
        $data = $request->validate([
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'distinct', Rule::exists('roles', 'id')],
        ]);

        $sync->handle($this->actor($request), $user, array_values(array_map('intval', $data['roles'] ?? [])));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles updated.')]);

        return $user->isStaff()
            ? redirect()->route('admin.users.edit', $user)
            : redirect()->route('admin.users.index');
    }

    public function updateStatus(Request $request, User $user, SetUserDisabled $setDisabled): RedirectResponse
    {
        $data = $request->validate(['disabled' => ['required', 'boolean']]);

        $setDisabled->handle($this->actor($request), $user, (bool) $data['disabled']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['disabled'] ? __('User disabled.') : __('User enabled.')]);

        return redirect()->route('admin.users.edit', $user);
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'disabled' => $user->isDisabled(),
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Every role, flagged with whether the actor may grant/revoke it.
     *
     * @return list<array{id: int, name: string, display_name: string, description: string|null, grantable: bool}>
     */
    private function assignableRoles(User $actor): array
    {
        $actorPermissions = $actor->permissionNames();

        /** @var list<array{id: int, name: string, display_name: string, description: string|null, grantable: bool}> */
        return Role::query()->with('permissions:id,name')->orderBy('id')->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'grantable' => $actor->isOwner()
                    || (! $role->isOwner() && array_diff($role->permissionNames(), $actorPermissions) === []),
            ])
            ->values()
            ->all();
    }
}
