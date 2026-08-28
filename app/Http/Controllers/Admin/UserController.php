<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(array_keys(User::ROLE_LABELS))],
            'status' => ['nullable', Rule::in(array_keys(User::STATUS_LABELS))],
        ]);

        $users = User::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("FIELD(role, 'super_admin', 'nstp_admin', 'coordinator', 'facilitator', 'student')")
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $roleCounts = User::query()
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.users.index', compact('users', 'roleCounts', 'filters'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->accountRules());

        $user = User::create([
            'name' => $validated['name'],
            'email' => str($validated['email'])->lower()->toString(),
            'password' => $validated['password'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'must_change_password' => true,
        ]);

        return redirect()->route('admin.users.edit', $user)
            ->with('status', "Nagawa na ang {$user->roleLabel()} account ni {$user->name}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->accountRules($user, false));

        if ($request->user()->is($user) && $validated['role'] !== 'super_admin') {
            throw ValidationException::withMessages(['role' => 'Hindi mo maaaring palitan ang sarili mong Super Admin role.']);
        }

        if ($request->user()->is($user) && $validated['status'] !== 'active') {
            throw ValidationException::withMessages(['status' => 'Hindi mo maaaring i-deactivate ang sarili mong account.']);
        }

        $this->ensureActiveSuperAdminRemains($user, $validated['role'], $validated['status']);

        $user->fill([
            'name' => $validated['name'],
            'email' => str($validated['email'])->lower()->toString(),
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Na-update ang user account.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['status' => 'Hindi mo maaaring i-deactivate ang sarili mong account.']);
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

        return back()->with('status', "Na-reset ang password ni {$user->name}. Temporary password na ito at kailangang palitan sa unang login.");
    }

    private function accountRules(?User $user = null, bool $includePassword = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLE_LABELS))],
            'status' => ['required', Rule::in(array_keys(User::STATUS_LABELS))],
        ];

        if ($includePassword) {
            $rules['password'] = ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()];
        }

        return $rules;
    }

    private function ensureActiveSuperAdminRemains(User $user, string $newRole, string $newStatus): void
    {
        $removesActiveSuperAdmin = $user->isSuperAdmin()
            && $user->isActive()
            && ($newRole !== 'super_admin' || $newStatus !== 'active');

        if ($removesActiveSuperAdmin && User::where('role', 'super_admin')->where('status', 'active')->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Kailangang may matirang kahit isang active Super Admin account.',
            ]);
        }
    }
}
