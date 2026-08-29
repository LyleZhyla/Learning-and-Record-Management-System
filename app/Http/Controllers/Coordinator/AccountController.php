<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    private const VISIBLE_ROLES = ['facilitator', 'student'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::VISIBLE_ROLES)],
        ]);
        $componentId = $request->user()->nstp_component_id ?? 0;

        $accounts = $this->visibleAccounts($componentId)
            ->with([
                'facilitatedSections' => fn ($query) => $query->where('component_id', $componentId)->with('component'),
                'nstpEnrollments' => fn ($query) => $query->where('component_id', $componentId)->with(['component', 'section'])->latest('academic_year')->latest('semester'),
            ])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->orderByRaw("CASE role WHEN 'facilitator' THEN 1 WHEN 'student' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roleCounts = $this->visibleAccounts($componentId)
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('coordinator.accounts.index', compact('accounts', 'roleCounts', 'filters'));
    }

    public function show(Request $request, User $user): View
    {
        $componentId = $request->user()->nstp_component_id ?? 0;
        abort_unless($this->visibleAccounts($componentId)->whereKey($user)->exists(), 404);

        if ($user->isStudent()) {
            $user->load([
                'nstpEnrollments' => fn ($query) => $query->where('component_id', $componentId)->with(['component', 'section']),
                'attendanceRecords' => fn ($query) => $query
                    ->whereHas('attendanceSession.section', fn ($section) => $section->where('component_id', $componentId))
                    ->with('attendanceSession.section.component')->latest('checked_in_at'),
                'assessmentSubmissions' => fn ($query) => $query
                    ->whereHas('assessment.section', fn ($section) => $section->where('component_id', $componentId))
                    ->with('assessment.section.component')->latest('submitted_at'),
            ]);
        } else {
            $user->load(['facilitatedSections' => fn ($query) => $query->where('component_id', $componentId)->with('component')]);
        }

        return view('coordinator.accounts.show', compact('user'));
    }

    private function visibleAccounts(int $componentId): Builder
    {
        return User::query()
            ->where(fn ($query) => $query
                ->where(fn ($facilitators) => $facilitators
                    ->where('role', 'facilitator')
                    ->whereHas('facilitatedSections', fn ($sections) => $sections->where('component_id', $componentId)))
                ->orWhere(fn ($students) => $students
                    ->where('role', 'student')
                    ->whereHas('nstpEnrollments', fn ($enrollments) => $enrollments->where('component_id', $componentId))));
    }
}
