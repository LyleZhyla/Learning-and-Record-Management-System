<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    private const STAFF_ROLES = ['coordinator', 'facilitator'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::STAFF_ROLES)],
        ]);

        $accounts = User::query()
            ->whereIn('role', self::STAFF_ROLES)
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

        $roleCounts = User::whereIn('role', self::STAFF_ROLES)
            ->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('nstp_admin.accounts.index', [
            'accounts' => $accounts,
            'roleCounts' => $roleCounts,
            'filters' => $filters,
        ]);
    }

    public function show(User $user): View
    {
        abort_unless(in_array($user->role, [...self::STAFF_ROLES, 'student'], true), 404);

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

        $components = collect([$user->nstpComponent])->filter();

        if ($user->isFacilitator() && $components->isEmpty()) {
            $components = $user->facilitatedSections->pluck('component')->filter()->unique('id')->values();
        }

        $currentComponentId = $user->nstp_component_id ?? $components->first()?->id;
        $availableComponents = NstpComponent::query()->where('is_active', true)->orderBy('code')->get();

        return view('nstp_admin.accounts.show', [
            'user' => $user,
            'components' => $components,
            'currentComponentId' => $currentComponentId,
            'availableComponents' => $availableComponents,
            'rotcCategories' => NstpEnrollment::ROTC_CATEGORIES,
        ]);
    }

    public function updateComponent(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isCoordinator() || $user->isFacilitator(), 404);

        $validated = $request->validate([
            'nstp_component_id' => [
                'nullable',
                'integer',
                Rule::exists('nstp_components', 'id')->where('is_active', true),
            ],
        ]);

        $user->update(['nstp_component_id' => $validated['nstp_component_id'] ?? null]);

        $message = $user->nstp_component_id
            ? "{$user->name}'s NSTP component assignment was updated."
            : "{$user->name}'s NSTP component assignment was removed.";

        return back()->with('status', $message);
    }

    public function updateRotcCategory(Request $request, User $user, NstpEnrollment $enrollment): RedirectResponse
    {
        abort_unless($user->isStudent() && $enrollment->student_id === $user->id, 404);
        $enrollment->loadMissing('component');
        abort_unless($enrollment->component?->code === 'ROTC', 404);

        $validated = $request->validate([
            'rotc_category' => ['required', Rule::in(array_keys(NstpEnrollment::ROTC_CATEGORIES))],
        ]);

        $this->assignRotcCategory($enrollment, $validated['rotc_category'], $request->user());

        return back()->with('status', $user->name.' was assigned to '.$validated['rotc_category'].'.');
    }

    public function bulkAssignStudents(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nstp_component_id' => [
                'required',
                'integer',
                Rule::exists('nstp_components', 'id')->where('is_active', true),
            ],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'student')
                    ->where('status', 'active')),
            ],
        ]);

        $studentIds = array_values(array_unique($validated['student_ids']));
        $componentId = (int) $validated['nstp_component_id'];
        $academicYear = $this->currentAcademicYear();
        $semester = $this->currentSemester();

        DB::transaction(function () use ($studentIds, $componentId, $academicYear, $semester): void {
            foreach ($studentIds as $studentId) {
                $enrollment = NstpEnrollment::firstOrNew([
                    'student_id' => $studentId,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                ]);

                if ($enrollment->exists && $enrollment->component_id !== $componentId) {
                    $enrollment->section_id = null;
                }

                $enrollment->fill([
                    'component_id' => $componentId,
                    'status' => 'enrolled',
                ])->save();
            }
        });

        return back()->with('status', count($studentIds).' student(s) assigned to the selected component.');
    }

    private function currentAcademicYear(): string
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return $start.'-'.($start + 1);
    }

    private function currentSemester(): string
    {
        return now()->month >= 6 ? 'first' : 'second';
    }

    private function assignRotcCategory(NstpEnrollment $enrollment, string $category, User $assignedBy): void
    {
        $advanced = in_array($category, ['MS-31', 'MS-41'], true);

        $enrollment->update([
            'rotc_category' => $category,
            'rotc_approval_status' => $advanced ? 'approved' : null,
            'rotc_approved_by' => $advanced ? $assignedBy->id : null,
            'rotc_approved_at' => $advanced ? now() : null,
            'status' => 'enrolled',
        ]);
    }
}
