<?php

namespace App\Http\Controllers;

use App\Models\FacilitatorRequirement;
use App\Models\FacilitatorRequirementSubmission;
use App\Models\NstpComponent;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FacilitatorRequirementController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if ($user->isFacilitator()) {
            $requirements = $this->requirementsForFacilitator($user)->with('component')->get();
            $submissions = FacilitatorRequirementSubmission::with(['requirement', 'reviewer'])
                ->where('facilitator_id', $user->id)->get()->keyBy('facilitator_requirement_id');
            $required = $requirements->where('is_required', true);
            $verified = $required->filter(fn (FacilitatorRequirement $requirement) => $submissions->get($requirement->id)?->status === 'verified')->count();

            return view('facilitator-requirements.index', $this->viewData($request) + [
                'requirements' => $requirements,
                'submissions' => $submissions,
                'requiredCount' => $required->count(),
                'verifiedCount' => $verified,
                'compliancePercentage' => $required->isEmpty() ? 100 : (int) round(($verified / $required->count()) * 100),
                'isConfigurator' => false,
                'complianceRows' => collect(),
            ]);
        }

        abort_unless($this->canReview($user), 403);
        $requirementsQuery = $this->requirementsForReviewer($user)->with('component')->orderBy('sort_order')->orderBy('title');
        $requirements = $requirementsQuery->get();
        $facilitators = User::where('role', 'facilitator')->where('status', 'active')
            ->with(['facilitatedSections.component', 'facilitatorRequirementSubmissions.requirement'])
            ->when($user->isCoordinator(), fn (Builder $query) => $query->whereHas('facilitatedSections', fn (Builder $sections) => $sections->where('component_id', $user->nstp_component_id ?? 0)))
            ->orderBy('name')->get();
        $submissions = FacilitatorRequirementSubmission::with(['requirement.component', 'facilitator.facilitatedSections', 'reviewer'])
            ->whereIn('facilitator_requirement_id', $requirements->pluck('id'))
            ->when($user->isCoordinator(), fn (Builder $query) => $query->whereHas('facilitator.facilitatedSections', fn (Builder $sections) => $sections->where('component_id', $user->nstp_component_id ?? 0)))
            ->latest('submitted_at')->get();
        $complianceRows = $facilitators->map(function (User $facilitator) use ($requirements): array {
            $componentIds = $facilitator->facilitatedSections->pluck('component_id');
            $applicable = $requirements->where('is_active', true)->where('is_required', true)
                ->filter(fn (FacilitatorRequirement $requirement) => $requirement->component_id === null || $componentIds->contains($requirement->component_id));
            $submissions = $facilitator->facilitatorRequirementSubmissions->keyBy('facilitator_requirement_id');
            $verified = $applicable->filter(fn (FacilitatorRequirement $requirement) => $submissions->get($requirement->id)?->status === 'verified')->count();

            return [
                'facilitator' => $facilitator,
                'required' => $applicable->count(),
                'verified' => $verified,
                'pending' => $applicable->filter(fn (FacilitatorRequirement $requirement) => $submissions->get($requirement->id)?->status === 'pending')->count(),
                'correction' => $applicable->filter(fn (FacilitatorRequirement $requirement) => in_array($submissions->get($requirement->id)?->status, ['needs_correction', 'rejected'], true))->count(),
                'missing' => $applicable->filter(fn (FacilitatorRequirement $requirement) => ! $submissions->has($requirement->id))->count(),
                'percentage' => $applicable->isEmpty() ? 100 : (int) round(($verified / $applicable->count()) * 100),
            ];
        });

        return view('facilitator-requirements.index', $this->viewData($request) + [
            'requirements' => $requirements,
            'submissions' => $submissions,
            'complianceRows' => $complianceRows,
            'isConfigurator' => $user->isSuperAdmin() || $user->isNstpAdmin(),
            'components' => NstpComponent::where('is_active', true)->orderBy('code')->get(),
            'extensionOptions' => FacilitatorRequirement::ALLOWED_EXTENSIONS,
        ]);
    }

    public function storeRequirement(Request $request): RedirectResponse
    {
        $this->ensureConfigurator($request->user());
        $validated = $this->requirementData($request);
        FacilitatorRequirement::create($validated + [
            'slug' => FacilitatorRequirement::uniqueSlug($validated['title']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Facilitator requirement created.');
    }

    public function updateRequirement(Request $request, FacilitatorRequirement $facilitatorRequirement): RedirectResponse
    {
        $this->ensureConfigurator($request->user());
        $validated = $this->requirementData($request);
        $facilitatorRequirement->update($validated + [
            'slug' => FacilitatorRequirement::uniqueSlug($validated['title'], $facilitatorRequirement->id),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Facilitator requirement updated.');
    }

    public function submit(Request $request, FacilitatorRequirement $facilitatorRequirement): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator(), 403);
        abort_unless($this->requirementsForFacilitator($request->user())->whereKey($facilitatorRequirement->id)->exists(), 403);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $facilitatorRequirement->accepted_extensions), 'max:'.$facilitatorRequirement->max_size_kb],
            'facilitator_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $request->file('file');
        $path = $file->store('facilitator-requirements/'.$request->user()->id, 'local');
        abort_unless($path, 500, 'The requirement file could not be stored.');
        $existing = FacilitatorRequirementSubmission::where([
            'facilitator_requirement_id' => $facilitatorRequirement->id,
            'facilitator_id' => $request->user()->id,
        ])->first();
        $oldPath = $existing?->file_path;
        try {
            FacilitatorRequirementSubmission::updateOrCreate([
                'facilitator_requirement_id' => $facilitatorRequirement->id,
                'facilitator_id' => $request->user()->id,
            ], [
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'facilitator_notes' => $validated['facilitator_notes'] ?? null,
                'status' => 'pending',
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', $existing ? 'Corrected requirement resubmitted for verification.' : 'Requirement submitted for verification.');
    }

    public function review(Request $request, FacilitatorRequirementSubmission $submission): RedirectResponse
    {
        $this->ensureCanReviewSubmission($request->user(), $submission);
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(FacilitatorRequirementSubmission::STATUSES))],
            'review_notes' => [Rule::requiredIf(in_array($request->input('status'), ['needs_correction', 'rejected'], true)), 'nullable', 'string', 'max:2000'],
        ]);
        $submission->update($validated + [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Facilitator requirement review saved.');
    }

    public function download(Request $request, FacilitatorRequirementSubmission $submission): StreamedResponse
    {
        abort_unless(
            ($request->user()->isFacilitator() && $submission->facilitator_id === $request->user()->id)
            || $this->canReviewSubmission($request->user(), $submission),
            403,
        );
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->original_name);
    }

    private function requirementData(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'accepted_extensions' => ['required', 'array', 'min:1'],
            'accepted_extensions.*' => [Rule::in(FacilitatorRequirement::ALLOWED_EXTENSIONS)],
            'max_size_mb' => ['required', 'integer', 'between:1,25'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ]);
        $validated['accepted_extensions'] = array_values(array_unique($validated['accepted_extensions']));
        $validated['max_size_kb'] = (int) $validated['max_size_mb'] * 1024;
        unset($validated['max_size_mb']);

        return $validated;
    }

    private function requirementsForFacilitator(User $facilitator): Builder
    {
        $componentIds = $facilitator->facilitatedSections()->pluck('component_id');

        return FacilitatorRequirement::where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('component_id')->orWhereIn('component_id', $componentIds))
            ->orderBy('sort_order')->orderBy('title');
    }

    private function requirementsForReviewer(User $user): Builder
    {
        $query = FacilitatorRequirement::query();
        if ($user->isCoordinator()) {
            $query->where('is_active', true)->where(fn (Builder $scope) => $scope
                ->whereNull('component_id')->orWhere('component_id', $user->nstp_component_id ?? 0));
        }

        return $query;
    }

    private function ensureCanReviewSubmission(User $user, FacilitatorRequirementSubmission $submission): void
    {
        abort_unless($this->canReviewSubmission($user, $submission), 403);
    }

    private function canReviewSubmission(User $user, FacilitatorRequirementSubmission $submission): bool
    {
        if ($user->isSuperAdmin() || $user->isNstpAdmin()) {
            return true;
        }
        if (! $user->isCoordinator()) {
            return false;
        }
        $submission->loadMissing(['requirement', 'facilitator.facilitatedSections']);

        return ($submission->requirement->component_id !== null
                && $submission->requirement->component_id === $user->nstp_component_id)
            || ($submission->requirement->component_id === null
                && $submission->facilitator->facilitatedSections->contains('component_id', $user->nstp_component_id));
    }

    private function canReview(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isNstpAdmin() || $user->isCoordinator();
    }

    private function ensureConfigurator(User $user): void
    {
        abort_unless($user->isSuperAdmin() || $user->isNstpAdmin(), 403);
    }

    private function viewData(Request $request): array
    {
        return [
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->access->routePrefix($request->user()),
            'statuses' => FacilitatorRequirementSubmission::STATUSES,
        ];
    }
}
