<?php

namespace App\Http\Controllers;

use App\Models\CommunityProject;
use App\Models\CommunityProjectActivity;
use App\Models\CommunityProjectDocument;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityProjectController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'approval_status' => ['nullable', Rule::in(array_keys(CommunityProject::APPROVAL_STATUSES))],
            'implementation_status' => ['nullable', Rule::in(array_keys(CommunityProject::IMPLEMENTATION_STATUSES))],
        ]);
        $projects = $this->visibleProjects($request)
            ->with(['component', 'section', 'proposer'])
            ->withCount('activities')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $nested) => $nested
                ->where('title', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('beneficiaries', 'like', "%{$search}%")))
            ->when($filters['approval_status'] ?? null, fn (Builder $query, string $status) => $query->where('approval_status', $status))
            ->when($filters['implementation_status'] ?? null, fn (Builder $query, string $status) => $query->where('implementation_status', $status))
            ->latest()->paginate(15)->withQueryString();

        return view('community-projects.index', $this->viewContext($request) + compact('projects', 'filters'));
    }

    public function create(Request $request): View
    {
        $enrollment = $request->user()->isStudent() ? $this->access->currentEnrollment($request->user()) : null;
        if ($request->user()->isStudent() && ! $enrollment) {
            throw ValidationException::withMessages(['project' => 'Complete your NSTP enrollment before submitting a community project proposal.']);
        }

        return view('community-projects.form', $this->viewContext($request) + [
            'project' => new CommunityProject,
            'components' => $this->availableComponents($request),
            'sections' => $this->availableSections($request),
            'enrollment' => $enrollment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->projectData($request);
        [$componentId, $sectionId] = $this->resolveScope($request, $validated);

        $project = DB::transaction(function () use ($request, $validated, $componentId, $sectionId): CommunityProject {
            $project = CommunityProject::create([
                ...$validated,
                'component_id' => $componentId,
                'section_id' => $sectionId,
                'proposed_by' => $request->user()->id,
                'approval_status' => 'pending',
                'implementation_status' => 'proposed',
            ]);
            $project->update(['reference_number' => sprintf('CP-%s-%05d', now()->format('Y'), $project->id)]);

            return $project;
        });

        return redirect()->route($this->routePrefix($request).'.community-projects.show', $project)
            ->with('status', 'Community project proposal created and submitted for review.');
    }

    public function show(Request $request, CommunityProject $communityProject): View
    {
        $this->ensureVisible($request, $communityProject);
        $communityProject->load([
            'component', 'section.facilitator', 'proposer', 'approver', 'activities.creator',
            'documents.activity', 'documents.uploader',
        ]);

        return view('community-projects.show', $this->viewContext($request) + [
            'project' => $communityProject,
            'canEdit' => $this->canEdit($request, $communityProject),
            'canApprove' => $this->canApprove($request, $communityProject),
            'canManageImplementation' => $this->canManageImplementation($request, $communityProject),
            'canUploadDocumentation' => $this->canUploadDocumentation($request, $communityProject),
        ]);
    }

    public function edit(Request $request, CommunityProject $communityProject): View
    {
        abort_unless($this->canEdit($request, $communityProject), 403);

        return view('community-projects.form', $this->viewContext($request) + [
            'project' => $communityProject,
            'components' => $this->availableComponents($request),
            'sections' => $this->availableSections($request),
            'enrollment' => $request->user()->isStudent() ? $this->access->currentEnrollment($request->user()) : null,
        ]);
    }

    public function update(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        abort_unless($this->canEdit($request, $communityProject), 403);
        $validated = $this->projectData($request);
        [$componentId, $sectionId] = $this->resolveScope($request, $validated);
        $updates = [...$validated, 'component_id' => $componentId, 'section_id' => $sectionId];
        if ($request->user()->isStudent() && $communityProject->approval_status === 'needs_revision') {
            $updates['approval_status'] = 'pending';
            $updates['approval_notes'] = null;
        }
        $communityProject->update($updates);

        return redirect()->route($this->routePrefix($request).'.community-projects.show', $communityProject)
            ->with('status', 'Community project details updated.');
    }

    public function updateApproval(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        abort_unless($this->canApprove($request, $communityProject), 403);
        $validated = $request->validate([
            'approval_status' => ['required', Rule::in(['approved', 'needs_revision', 'rejected'])],
            'approval_notes' => [Rule::requiredIf($request->input('approval_status') !== 'approved'), 'nullable', 'string', 'max:3000'],
        ]);
        $communityProject->update([
            ...$validated,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'implementation_status' => $validated['approval_status'] === 'approved' && $communityProject->implementation_status === 'proposed'
                ? 'planning'
                : $communityProject->implementation_status,
        ]);

        return back()->with('status', 'Project approval decision recorded.');
    }

    public function updateImplementation(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        abort_unless($this->canManageImplementation($request, $communityProject), 403);
        $validated = $request->validate([
            'implementation_status' => ['required', Rule::in(array_keys(CommunityProject::IMPLEMENTATION_STATUSES))],
            'implementation_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        if ($communityProject->approval_status !== 'approved' && in_array($validated['implementation_status'], ['planning', 'ongoing', 'completed'], true)) {
            throw ValidationException::withMessages(['implementation_status' => 'The project must be approved before implementation can proceed.']);
        }
        $communityProject->update($validated);

        return back()->with('status', 'Implementation status updated.');
    }

    public function storeActivity(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        abort_unless($this->canManageImplementation($request, $communityProject), 403);
        $validated = $this->activityData($request);
        $communityProject->activities()->create([
            ...$validated,
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Project activity added.');
    }

    public function updateActivity(Request $request, CommunityProject $communityProject, CommunityProjectActivity $activity): RedirectResponse
    {
        abort_unless($activity->community_project_id === $communityProject->id && $this->canManageImplementation($request, $communityProject), 403);
        $validated = $this->activityData($request);
        $activity->update([
            ...$validated,
            'completed_at' => $validated['status'] === 'completed' ? ($activity->completed_at ?? now()) : null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Project activity updated.');
    }

    public function storeDocument(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        $this->ensureVisible($request, $communityProject);
        abort_unless($this->canUploadDocumentation($request, $communityProject), 403);
        abort_unless($communityProject->approval_status === 'approved', 422, 'Approve the project before uploading accomplishment documentation.');

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(CommunityProjectDocument::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'community_project_activity_id' => [
                'nullable',
                'integer',
                Rule::exists('community_project_activities', 'id')->where(
                    fn ($query) => $query->where('community_project_id', $communityProject->id)
                ),
            ],
            'file' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv'],
        ]);

        $file = $request->file('file');
        $path = $file->store('community-project-documents/'.$communityProject->id, 'local');
        abort_unless($path, 500, 'The project document could not be stored.');

        try {
            $communityProject->documents()->create([
                ...collect($validated)->except('file')->all(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return back()->with('status', 'Project accomplishment documentation uploaded.');
    }

    public function downloadDocument(Request $request, CommunityProject $communityProject, CommunityProjectDocument $document): StreamedResponse
    {
        $this->ensureVisible($request, $communityProject);
        abort_unless($document->community_project_id === $communityProject->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(Request $request, CommunityProject $communityProject, CommunityProjectDocument $document): RedirectResponse
    {
        $this->ensureVisible($request, $communityProject);
        abort_unless($document->community_project_id === $communityProject->id, 404);
        abort_unless(
            $this->canManageImplementation($request, $communityProject)
            || $document->uploaded_by === $request->user()->id,
            403,
        );

        $path = $document->file_path;
        $document->delete();
        Storage::disk('local')->delete($path);

        return back()->with('status', 'Project documentation removed.');
    }

    private function projectData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'component_id' => [Rule::requiredIf(! $request->user()->isStudent()), 'nullable', 'integer', 'exists:nstp_components,id'],
            'section_id' => [Rule::requiredIf($request->user()->isFacilitator()), 'nullable', 'integer', 'exists:nstp_sections,id'],
            'description' => ['required', 'string', 'max:5000'],
            'objectives' => ['required', 'string', 'max:5000'],
            'beneficiaries' => ['required', 'string', 'max:2000'],
            'beneficiary_count' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'location' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }

    private function activityData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'scheduled_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(CommunityProjectActivity::STATUSES))],
            'accomplishment_notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function resolveScope(Request $request, array $validated): array
    {
        $user = $request->user();
        if ($user->isStudent()) {
            $enrollment = $this->access->currentEnrollment($user);
            abort_unless($enrollment, 422, 'Complete NSTP enrollment first.');

            return [$enrollment->component_id, $enrollment->section_id];
        }
        $componentId = (int) ($validated['component_id'] ?? 0);
        $section = filled($validated['section_id'] ?? null) ? NstpSection::findOrFail($validated['section_id']) : null;
        if ($section && $section->component_id !== $componentId) {
            throw ValidationException::withMessages(['section_id' => 'The selected section does not belong to the selected component.']);
        }
        abort_unless(
            $user->isSuperAdmin()
            || $user->isNstpAdmin()
            || ($user->isCoordinator() && $componentId === $user->nstp_component_id)
            || ($user->isFacilitator() && $section?->facilitator_id === $user->id),
            403,
        );

        return [$componentId, $section?->id];
    }

    private function visibleProjects(Request $request): Builder
    {
        $user = $request->user();
        $query = CommunityProject::query();
        if ($user->isCoordinator()) {
            $query->where('component_id', $user->nstp_component_id ?? 0);
        } elseif ($user->isFacilitator()) {
            $query->whereHas('section', fn (Builder $section) => $section->where('facilitator_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->where(fn (Builder $scope) => $scope
                ->where('proposed_by', $user->id)
                ->orWhereHas('tasks', fn (Builder $task) => $task->where('assigned_to', $user->id)));
        } elseif (! $user->isSuperAdmin() && ! $user->isNstpAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function ensureVisible(Request $request, CommunityProject $project): void
    {
        abort_unless($this->visibleProjects($request)->whereKey($project)->exists(), 403);
    }

    private function canEdit(Request $request, CommunityProject $project): bool
    {
        if (! $this->visibleProjects($request)->whereKey($project)->exists()) {
            return false;
        }
        if ($request->user()->isStudent()) {
            return $project->proposed_by === $request->user()->id
                && in_array($project->approval_status, ['pending', 'needs_revision'], true)
                && $project->implementation_status === 'proposed';
        }

        return true;
    }

    private function canApprove(Request $request, CommunityProject $project): bool
    {
        $user = $request->user();

        return $user->isSuperAdmin() || $user->isNstpAdmin()
            || ($user->isCoordinator() && $project->component_id === $user->nstp_component_id);
    }

    private function canManageImplementation(Request $request, CommunityProject $project): bool
    {
        $user = $request->user();

        return $this->canApprove($request, $project)
            || ($user->isFacilitator() && $project->section?->facilitator_id === $user->id);
    }

    private function canUploadDocumentation(Request $request, CommunityProject $project): bool
    {
        if ($this->canManageImplementation($request, $project)) {
            return true;
        }

        return $request->user()->isStudent()
            && $this->visibleProjects($request)->whereKey($project)->exists();
    }

    private function availableComponents(Request $request)
    {
        $query = NstpComponent::where('is_active', true)->orderBy('code');
        if ($request->user()->isCoordinator()) {
            $query->whereKey($request->user()->nstp_component_id ?? 0);
        } elseif ($request->user()->isFacilitator()) {
            $query->whereHas('sections', fn (Builder $section) => $section->where('facilitator_id', $request->user()->id));
        }

        return $query->get();
    }

    private function availableSections(Request $request)
    {
        $query = NstpSection::with('component')->orderByDesc('academic_year')->orderBy('code');
        if ($request->user()->isCoordinator()) {
            $query->where('component_id', $request->user()->nstp_component_id ?? 0);
        } elseif ($request->user()->isFacilitator()) {
            $query->where('facilitator_id', $request->user()->id);
        } elseif ($request->user()->isStudent()) {
            $enrollment = $this->access->currentEnrollment($request->user());
            $query->whereKey($enrollment?->section_id ?? 0);
        }

        return $query->get();
    }

    private function viewContext(Request $request): array
    {
        return [
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->routePrefix($request),
            'approvalStatuses' => CommunityProject::APPROVAL_STATUSES,
            'implementationStatuses' => CommunityProject::IMPLEMENTATION_STATUSES,
            'activityStatuses' => CommunityProjectActivity::STATUSES,
            'documentCategories' => CommunityProjectDocument::CATEGORIES,
        ];
    }

    private function routePrefix(Request $request): string
    {
        return $this->access->routePrefix($request->user());
    }
}
