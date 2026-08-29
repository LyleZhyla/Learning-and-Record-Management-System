<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    private const VISIBLE_ROLES = ['coordinator', 'facilitator', 'student'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::VISIBLE_ROLES)],
        ]);

        $accounts = User::query()
            ->whereIn('role', self::VISIBLE_ROLES)
            ->with([
                'facilitatedSections.component',
                'nstpComponent',
                'nstpEnrollments' => fn ($query) => $query->with(['component', 'section'])->latest('academic_year')->latest('semester'),
            ])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->orderByRaw("CASE role WHEN 'coordinator' THEN 1 WHEN 'facilitator' THEN 2 WHEN 'student' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roleCounts = User::whereIn('role', self::VISIBLE_ROLES)
            ->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('nstp_admin.accounts.index', compact('accounts', 'roleCounts', 'filters'));
    }

    public function show(User $user): View
    {
        abort_unless(in_array($user->role, self::VISIBLE_ROLES, true), 404);

        if ($user->isStudent()) {
            $user->load([
                'nstpEnrollments.component',
                'nstpEnrollments.section',
                'attendanceRecords' => fn ($query) => $query->with('attendanceSession.section.component')->latest('checked_in_at'),
                'assessmentSubmissions' => fn ($query) => $query->with('assessment.section.component')->latest('submitted_at'),
            ]);
        } else {
            $user->load(['facilitatedSections.component', 'nstpComponent']);
        }

        $components = $user->isCoordinator()
            ? collect([$user->nstpComponent])->filter()
            : $user->facilitatedSections->pluck('component')->filter()->unique('id')->values();

        return view('nstp_admin.accounts.show', compact('user', 'components'));
    }
}
