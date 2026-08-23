<?php

namespace App\Core\Controllers;

use App\Core\Auth\Models\Permission;
use App\Core\Auth\Models\Role;
use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClinicUserController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Clinic/Users/Index', [
            'users' => User::with(['roles:id,name', 'branches:id,name'])->orderBy('name')->get([
                'id', 'name', 'email', 'phone', 'status', 'last_login_at', 'created_at',
            ]),
            'roles' => Role::with('permissions:id,name')->orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'password' => $validated['password'],
        ]);
        $user->syncRoles([$validated['role']]);
        $user->branches()->sync($validated['branch_ids'] ?? []);

        $this->auditLogger->logTenant('users.created', User::class, $user->id, [
            'role' => $validated['role'],
            'branch_ids' => $validated['branch_ids'] ?? [],
        ]);

        return redirect()->back()->with('success', 'Usuario creado y permisos asignados.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $validated = $this->validateUser($request, $user);
        $currentUserId = Auth::guard('web')->id();

        if ($currentUserId === $user->id && $validated['status'] !== 'active') {
            throw ValidationException::withMessages(['status' => 'No puede desactivar su propia cuenta.']);
        }

        $removesOwner = $user->hasRole('Owner') && ($validated['role'] !== 'Owner' || $validated['status'] !== 'active');
        if ($removesOwner && User::role('Owner')->where('status', 'active')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'La clínica debe conservar al menos un propietario activo.']);
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
        ]);
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();
        $user->syncRoles([$validated['role']]);
        $user->branches()->sync($validated['branch_ids'] ?? []);

        $this->auditLogger->logTenant('users.updated', User::class, $user->id, [
            'role' => $validated['role'],
            'status' => $validated['status'],
            'branch_ids' => $validated['branch_ids'] ?? [],
        ]);

        return redirect()->back()->with('success', 'Usuario y alcance actualizados.');
    }

    public function updateRole(Request $request, string $id): RedirectResponse
    {
        $role = Role::findOrFail($id);
        if ($role->name === 'Owner') {
            throw ValidationException::withMessages(['permissions' => 'Los permisos del propietario son obligatorios y no pueden reducirse.']);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::exists('tenant.permissions', 'name')->where('guard_name', 'web')],
        ]);
        $role->syncPermissions($validated['permissions']);

        $this->auditLogger->logTenant('roles.permissions_updated', Role::class, $role->id, [
            'role' => $role->name,
            'permissions' => $validated['permissions'],
        ]);

        return redirect()->back()->with('success', 'Permisos del rol actualizados.');
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive', 'locked'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'max:255'],
            'role' => ['required', 'string', Rule::exists('tenant.roles', 'name')->where('guard_name', 'web')],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['uuid', Rule::exists('tenant.branches', 'id')->whereNull('deleted_at')],
        ]);
    }
}
