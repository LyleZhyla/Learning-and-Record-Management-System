<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()->withCount('users')
            ->orderByRaw("CASE base_role WHEN 'super_admin' THEN 1 WHEN 'nstp_admin' THEN 2 WHEN 'coordinator' THEN 3 WHEN 'facilitator' THEN 4 WHEN 'student' THEN 5 ELSE 6 END")
            ->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.roles.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $role = Role::create($validated + ['slug' => $this->uniqueSlug($validated['name']), 'is_system' => false]);

        return redirect()->route('admin.roles.edit', $role)->with('status', "The {$role->name} role was created.");
    }

    public function edit(Role $role): View
    {
        abort_if($role->base_role === 'super_admin', 403, 'The Super Admin role always has full system access.');

        return view('admin.roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->base_role === 'super_admin', 403, 'The Super Admin role always has full system access.');
        $validated = $this->validated($request, $role);
        $role->update($validated);

        return back()->with('status', "The {$role->name} permission matrix was updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 422, 'Built-in roles cannot be deleted.');

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Reassign all accounts using this role before deleting it.']);
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', "The {$name} role was deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        $baseRole = $role?->base_role ?? (string) $request->input('base_role');
        $definitions = config('role_permissions.definitions', []);
        $allowed = collect($definitions)->filter(fn (array $definition): bool => in_array($baseRole, $definition['bases'], true))->keys()->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'base_role' => [$role ? 'nullable' : 'required', Rule::in(Role::CUSTOMIZABLE_BASE_ROLES)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in($allowed)],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['base_role'] = $baseRole;
        $validated['permissions'] = array_values(array_unique($validated['permissions'] ?? []));

        return $validated;
    }

    /** @return array<string, mixed> */
    private function formData(?Role $role = null): array
    {
        return [
            'role' => $role,
            'baseRoles' => collect(Role::CUSTOMIZABLE_BASE_ROLES)->mapWithKeys(fn (string $base): array => [$base => \App\Models\User::ROLE_LABELS[$base]])->all(),
            'permissionGroups' => collect(config('role_permissions.definitions', []))->groupBy('group'),
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'custom-role';
        $slug = $base;
        $suffix = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
