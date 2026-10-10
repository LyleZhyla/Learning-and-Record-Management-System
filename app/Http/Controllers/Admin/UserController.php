<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountCredentialMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    private const STAFF_ROLES = ['super_admin', 'nstp_admin', 'coordinator', 'facilitator'];

    private const CREATABLE_STAFF_ROLES = ['nstp_admin', 'coordinator', 'facilitator'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::STAFF_ROLES)],
            'status' => ['nullable', Rule::in(array_keys(User::STATUS_LABELS))],
        ]);

        $users = User::query()->with(['facilitatorProfile', 'nstpComponent'])
            ->whereIn('role', self::STAFF_ROLES)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('facilitatorProfile', fn ($profile) => $profile
                            ->where('contact_number', 'like', "%{$search}%"))
                        ->orWhereHas('nstpComponent', fn ($component) => $component
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE role WHEN 'super_admin' THEN 1 WHEN 'nstp_admin' THEN 2 WHEN 'coordinator' THEN 3 WHEN 'facilitator' THEN 4 WHEN 'student' THEN 5 ELSE 6 END")
            ->orderBy('name')
            ->paginate($this->perPage(10))
            ->withQueryString();

        $roleCounts = User::query()
            ->whereIn('role', self::STAFF_ROLES)
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.users.index', compact('users', 'roleCounts', 'filters'));
    }

    public function create(Request $request): View
    {
        $initialRole = $request->query('role', 'facilitator');
        abort_unless($initialRole === 'student' || in_array($initialRole, self::CREATABLE_STAFF_ROLES, true), 404);

        return view('admin.users.create', [
            'components' => NstpComponent::where('is_active', true)->orderBy('code')->get(),
            'initialRole' => $initialRole,
            'accessRoles' => $this->assignableRoles($initialRole === 'student' ? ['student'] : self::CREATABLE_STAFF_ROLES),
        ]);
    }

    public function store(Request $request, AccountCredentialMailer $credentialMailer): RedirectResponse
    {
        $this->synchronizeRequestedBaseRole($request);
        $validated = $request->validate($this->accountRules());
        $accessRole = $this->resolveAccessRole($validated);
        $validated['role'] = $accessRole?->base_role ?? $validated['role'];
        $validated['role_id'] = $accessRole?->id;

        if ($validated['role'] === 'facilitator') {
            $validated['status'] = 'active';
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user = DB::transaction(function () use ($validated, $temporaryPassword): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => str($validated['email'])->lower()->toString(),
                'password' => $temporaryPassword,
                'role' => $validated['role'],
                'role_id' => $validated['role_id'],
                'status' => $validated['status'],
                'nstp_component_id' => in_array($validated['role'], ['coordinator', 'facilitator'], true) ? ($validated['nstp_component_id'] ?? null) : null,
                'must_change_password' => true,
            ]);

            if ($user->isFacilitator()) {
                $user->facilitatorProfile()->create($this->facilitatorProfileData($validated));
            }

            return $user;
        });

        $emailSent = $credentialMailer->send($user, $temporaryPassword);

        $status = "The {$user->roleLabel()} account for {$user->name} was created successfully.";
        $status .= $emailSent
            ? ' The temporary login credentials were queued for email delivery.'
            : ' The credentials email could not be queued; copy the temporary credentials shown below.';

        return redirect()->route('admin.users.edit', $user)
            ->with([
                'status' => $status,
                'temporary_password' => $temporaryPassword,
                'temporary_password_email' => $user->email,
                'credentials_email_sent' => $emailSent,
            ]);
    }

    public function edit(User $user): View
    {
        $user->load('facilitatorProfile');

        return view('admin.users.edit', [
            'user' => $user,
            'components' => NstpComponent::where('is_active', true)->orderBy('code')->get(),
            'accessRoles' => Role::query()->where(fn ($query) => $query->where('is_active', true)->orWhereKey($user->role_id))->orderBy('base_role')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->synchronizeRequestedBaseRole($request);
        $validated = $request->validate($this->accountRules($user));
        $accessRole = $this->resolveAccessRole($validated);
        $validated['role'] = $accessRole?->base_role ?? $validated['role'];
        $validated['role_id'] = $accessRole?->id;

        if ($validated['role'] === 'facilitator') {
            $validated['status'] = $user->status;
        }

        if ($request->user()->is($user) && $validated['role'] !== 'super_admin') {
            throw ValidationException::withMessages(['role' => 'You cannot change your own Super Admin role.']);
        }

        if ($request->user()->is($user) && $validated['status'] !== 'active') {
            throw ValidationException::withMessages(['status' => 'You cannot deactivate your own account.']);
        }

        $this->ensureActiveSuperAdminRemains($user, $validated['role'], $validated['status']);

        DB::transaction(function () use ($user, $validated): void {
            $user->fill([
                'name' => $validated['name'],
                'email' => str($validated['email'])->lower()->toString(),
                'role' => $validated['role'],
                'role_id' => $validated['role_id'],
                'status' => $validated['status'],
                'nstp_component_id' => in_array($validated['role'], ['coordinator', 'facilitator'], true) ? ($validated['nstp_component_id'] ?? null) : null,
            ]);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();

            if ($user->isFacilitator()) {
                $user->facilitatorProfile()->updateOrCreate([], $this->facilitatorProfileData($validated));
            }
        });

        return back()->with('status', 'The user account was updated successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['status' => 'You cannot deactivate your own account.']);
        }

        $newStatus = $user->isActive() ? 'inactive' : 'active';
        $this->ensureActiveSuperAdminRemains($user, $user->role, $newStatus);
        $user->update(['status' => $newStatus]);

        if ($newStatus === 'inactive') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('status', "{$user->name} is now {$user->statusLabel()}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => true,
        ]);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()->with('status', "The password for {$user->name} was reset. This is a temporary password and must be changed at the next login.");
    }

    public function confirmDestroy(User $user): View
    {
        $this->ensureAccountCanBeDeleted($user);

        return view('admin.users.delete', compact('user'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureAccountCanBeDeleted($user);

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$user->email])],
        ], [
            'confirmation.in' => 'Enter the account email address exactly as shown to confirm permanent deletion.',
        ]);

        $actor = $request->user();
        $studentProfile = $user->studentProfile;
        $privateFilePaths = collect([
            $user->profile_photo_path,
            $studentProfile?->cor_path,
            $studentProfile?->formal_photo_path,
        ])->merge($user->documentSubmissions()->pluck('file_path'))->filter()->unique()->values();
        $deletedName = $user->name;
        $wasStudent = $user->isStudent();

        DB::transaction(function () use ($actor, $user): void {
            DB::table('attendance_sessions')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('learning_materials')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('assessments')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('omr_sheets')->where('created_by', $user->id)->update(['created_by' => $actor->id]);
            DB::table('omr_scan_results')->where('scanned_by', $user->id)->update(['scanned_by' => $actor->id]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        if ($privateFilePaths->isNotEmpty()) {
            Storage::disk('local')->delete($privateFilePaths->all());
        }

        $status = $wasStudent
            ? "The student account for {$deletedName} and its linked participation records were permanently deleted."
            : "The account for {$deletedName} was permanently deleted. Existing institutional records were preserved.";

        return redirect()->route($wasStudent ? 'admin.students.index' : 'admin.users.index')
            ->with('status', $status);
    }

    private function ensureAccountCanBeDeleted(User $user): void
    {
        abort_unless($user->canBePermanentlyDeleted(), 404);
    }

    private function accountRules(?User $user = null): array
    {
        $isFacilitator = request('role') === 'facilitator';

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLE_LABELS))],
            'access_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'status' => [Rule::requiredIf(! $isFacilitator), 'nullable', Rule::in(array_keys(User::STATUS_LABELS))],
            'nstp_component_id' => ['nullable', 'required_if:role,coordinator,facilitator', 'integer', Rule::exists('nstp_components', 'id')->where('is_active', true)],
            'contact_number' => [Rule::requiredIf($isFacilitator), 'nullable', 'regex:/^09[0-9]{9}$/'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function facilitatorProfileData(array $validated): array
    {
        return ['contact_number' => trim($validated['contact_number'])];
    }

    private function generateTemporaryPassword(): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghijkmnopqrstuvwxyz';
        $numbers = '23456789';
        $symbols = '!@#$%&*?';
        $pool = $uppercase.$lowercase.$numbers.$symbols;
        $characters = [
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        while (count($characters) < 16) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }

    private function ensureActiveSuperAdminRemains(User $user, string $newRole, string $newStatus): void
    {
        $removesActiveSuperAdmin = $user->isSuperAdmin()
            && $user->isActive()
            && ($newRole !== 'super_admin' || $newStatus !== 'active');

        if ($removesActiveSuperAdmin && User::where('role', 'super_admin')->where('status', 'active')->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'At least one active Super Admin account must remain in the system.',
            ]);
        }
    }

    /** @param array<int, string> $baseRoles */
    private function assignableRoles(array $baseRoles)
    {
        return Role::query()->where('is_active', true)->whereIn('base_role', $baseRoles)->orderBy('base_role')->orderBy('name')->get();
    }

    /** @param array<string, mixed> $validated */
    private function resolveAccessRole(array $validated): ?Role
    {
        if (! empty($validated['access_role_id'])) {
            return Role::query()->where('is_active', true)->findOrFail($validated['access_role_id']);
        }

        return Role::query()->where('slug', $validated['role'])->first();
    }

    private function synchronizeRequestedBaseRole(Request $request): void
    {
        if ($request->filled('access_role_id')) {
            $baseRole = Role::query()->where('is_active', true)->whereKey($request->integer('access_role_id'))->value('base_role');
            if ($baseRole) {
                $request->merge(['role' => $baseRole]);
            }
        }
    }
}
