<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\SpreadsheetDownloadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DirectoryExportController extends Controller
{
    public function __construct(private SpreadsheetDownloadService $downloads) {}

    public function staff(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['super_admin', 'nstp_admin', 'coordinator', 'facilitator'])],
            'status' => ['nullable', Rule::in(array_keys(User::STATUS_LABELS))],
        ]);
        $rows = User::with('nstpComponent')->whereIn('role', ['super_admin', 'nstp_admin', 'coordinator', 'facilitator'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('role')->orderBy('name')->get()->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleLabel(),
                'component' => $user->nstpComponent?->code ?? '—',
                'status' => $user->statusLabel(),
                'last_sign_in' => $user->last_login_at?->format('M d, Y h:i A') ?? 'Never',
            ]);

        return $this->downloads->download('Staff Account Directory', ['Name', 'Email', 'Role', 'Component', 'Status', 'Last Sign In'], $rows, 'Current directory filters');
    }

    public function students(Request $request): StreamedResponse
    {
        $componentValues = NstpComponent::pluck('id')->map(fn ($id) => (string) $id)->push('unassigned')->all();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(User::STATUS_LABELS))],
            'component' => ['nullable', Rule::in($componentValues)],
        ]);
        $rows = User::where('role', 'student')->with(['latestNstpEnrollment.component', 'latestNstpEnrollment.section'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['component'] ?? null, function ($query, string $component): void {
                $component === 'unassigned'
                    ? $query->whereDoesntHave('latestNstpEnrollment')
                    : $query->whereHas('latestNstpEnrollment', fn ($enrollment) => $enrollment->where('component_id', (int) $component));
            })
            ->orderBy('name')->get()->map(function (User $student): array {
                $enrollment = $student->latestNstpEnrollment;

                return [
                    'student' => $student->name,
                    'email' => $student->email,
                    'component' => $enrollment?->component?->code ?? 'Unassigned',
                    'section' => $enrollment?->section?->code ?? 'Unassigned',
                    'term' => $enrollment ? $enrollment->academic_year.' / '.str($enrollment->semester)->headline() : '—',
                    'status' => $student->statusLabel(),
                    'last_sign_in' => $student->last_login_at?->format('M d, Y h:i A') ?? 'Never',
                ];
            });

        return $this->downloads->download('Student Account Directory', ['Student', 'Email', 'Component', 'Section', 'Term', 'Status', 'Last Sign In'], $rows, 'Current directory filters');
    }

    public function components(Request $request): StreamedResponse
    {
        $rows = NstpComponent::withCount(['sections', 'enrollments'])
            ->when($request->user()->isCoordinator(), fn ($query) => $query->whereKey($request->user()->nstp_component_id ?? 0))
            ->orderBy('code')->get()->map(fn (NstpComponent $component) => [
                'code' => $component->code,
                'name' => $component->name,
                'description' => $component->description ?? '—',
                'sections' => $component->sections_count,
                'enrollments' => $component->enrollments_count,
                'default_capacity' => $component->default_section_capacity,
                'status' => $component->is_active ? 'Active' : 'Inactive',
            ]);

        return $this->downloads->download('NSTP Component Directory', ['Code', 'Name', 'Description', 'Sections', 'Enrollments', 'Default Capacity', 'Status'], $rows);
    }

    public function sections(Request $request): StreamedResponse
    {
        $rows = NstpSection::with(['component', 'facilitator'])->withCount('enrollments')
            ->when($request->user()->isCoordinator(), fn ($query) => $query->where('component_id', $request->user()->nstp_component_id ?? 0))
            ->when($request->user()->isFacilitator(), fn ($query) => $query->where('facilitator_id', $request->user()->id))
            ->orderByDesc('academic_year')->orderBy('code')->get()->map(fn (NstpSection $section) => [
                'section' => $section->code,
                'name' => $section->name,
                'component' => $section->component->code,
                'academic_year' => $section->academic_year,
                'semester' => $section->semesterLabel(),
                'facilitator' => $section->facilitator?->name ?? 'Unassigned',
                'enrollment' => $section->enrollments_count,
                'capacity' => $section->capacity,
                'status' => ucfirst($section->status),
            ]);

        return $this->downloads->download('NSTP Section Directory', ['Section', 'Name', 'Component', 'Academic Year', 'Semester', 'Facilitator', 'Enrollment', 'Capacity', 'Status'], $rows);
    }
}
