<?php

namespace App\Http\Controllers;

use App\Models\CommunityNeedPriority;
use App\Models\CommunityNeedsAssessment;
use App\Models\CommunityPartner;
use App\Models\CommunityProject;
use App\Models\PartnershipDocument;
use App\Services\InstitutionContext;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CommunityRegistryController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'partner_type' => ['nullable', Rule::in(array_keys(CommunityPartner::TYPES))],
            'category' => ['nullable', Rule::in(array_keys(CommunityNeedPriority::CATEGORIES))],
            'sdg' => ['nullable', Rule::in(array_keys(CommunityNeedPriority::SDGS))],
        ]);
        $partners = $this->visiblePartners($request)
            ->withCount(['assessments', 'projects'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%")))
            ->when($filters['partner_type'] ?? null, fn (Builder $query, string $type) => $query->where('partner_type', $type))
            ->orderBy('name')->paginate($this->perPage())->withQueryString();
        $needs = CommunityNeedPriority::query()
            ->whereHas('assessment', fn (Builder $query) => $query->where('status', 'verified')
                ->whereHas('partner', fn (Builder $partner) => $partner->where('verification_status', 'verified')->where('is_active', true)))
            ->with(['assessment.partner'])->withCount('projects')
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['sdg'] ?? null, fn (Builder $query, string $sdg) => $query->whereJsonContains('sdg_codes', $sdg))
            ->whereIn('status', ['open', 'matched'])->latest()->limit(30)->get();
        $matchedProjects = CommunityProject::query()->whereNotNull('community_need_priority_id')
            ->with(['partner', 'needPriority.assessment'])->latest()->limit(20)->get();

        return view('community-registry.index', $this->context($request) + compact('partners', 'needs', 'matchedProjects', 'filters'));
    }

    public function show(Request $request, CommunityPartner $communityPartner): View
    {
        $this->ensureVisible($request, $communityPartner);
        $communityPartner->load(['documents', 'assessments.priorities.projects']);
        $history = $communityPartner->projects()->with(['component', 'needPriority'])->latest()->get();

        return view('community-registry.show', $this->context($request) + [
            'partner' => $communityPartner,
            'history' => $history,
            'repeatProjectCount' => $history->count(),
            'beneficiaryHistoryTotal' => $history->sum('beneficiary_count'),
        ]);
    }

    public function storePartner(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $partner = CommunityPartner::create($this->partnerData($request) + ['verification_status' => 'pending']);

        return redirect()->route($this->prefix($request).'.community-registry.partners.show', $partner)
            ->with('status', 'Community partner profile created for verification.');
    }

    public function updatePartner(Request $request, CommunityPartner $communityPartner): RedirectResponse
    {
        $this->authorizeManager($request);
        $communityPartner->update($this->partnerData($request));

        return back()->with('status', 'Community partner profile updated.');
    }

    public function verifyPartner(Request $request, CommunityPartner $communityPartner): RedirectResponse
    {
        $this->authorizeManager($request);
        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(CommunityPartner::VERIFICATION_STATUSES)],
            'verification_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $communityPartner->update($validated + [
            'verified_by' => $validated['verification_status'] === 'verified' ? $request->user()->id : null,
            'verified_at' => $validated['verification_status'] === 'verified' ? now() : null,
        ]);

        return back()->with('status', 'Partner verification status updated.');
    }

    public function storeDocument(Request $request, CommunityPartner $communityPartner): RedirectResponse
    {
        $this->authorizeManager($request);
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(PartnershipDocument::TYPES))],
            'title' => ['required', 'string', 'max:180'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'effective_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:15360'],
        ]);
        $file = $request->file('file');
        $path = $file->store('partnership-documents/'.$communityPartner->id, 'local');
        abort_unless($path, 500, 'The partnership document could not be stored.');
        try {
            $communityPartner->documents()->create([
                ...collect($validated)->except('file')->all(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('status', 'Partnership document uploaded privately.');
    }

    public function downloadDocument(Request $request, CommunityPartner $communityPartner, PartnershipDocument $document): StreamedResponse
    {
        $this->authorizeManager($request);
        abort_unless($document->community_partner_id === $communityPartner->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function storeAssessment(Request $request, CommunityPartner $communityPartner): RedirectResponse
    {
        $this->authorizeManager($request);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'assessed_on' => ['required', 'date', 'before_or_equal:today'],
            'location' => ['required', 'string', 'max:255'],
            'beneficiary_group' => ['required', 'string', 'max:255'],
            'estimated_beneficiaries' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'assessment_methods' => ['required', 'string', 'max:5000'],
            'findings' => ['required', 'string', 'max:10000'],
        ]);
        $communityPartner->assessments()->create($validated + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return back()->with('status', 'Needs assessment recorded as draft.');
    }

    public function verifyAssessment(Request $request, CommunityPartner $communityPartner, CommunityNeedsAssessment $assessment): RedirectResponse
    {
        $this->authorizeManager($request);
        abort_unless($assessment->community_partner_id === $communityPartner->id, 404);
        abort_unless($communityPartner->verification_status === 'verified', 422, 'Verify the partner before verifying its assessment.');
        $status = $request->validate(['status' => ['required', Rule::in(CommunityNeedsAssessment::STATUSES)]])['status'];
        $assessment->update([
            'status' => $status,
            'verified_by' => $status === 'verified' ? $request->user()->id : null,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);

        return back()->with('status', 'Needs assessment status updated.');
    }

    public function storePriority(Request $request, CommunityPartner $communityPartner, CommunityNeedsAssessment $assessment): RedirectResponse
    {
        $this->authorizeManager($request);
        abort_unless($assessment->community_partner_id === $communityPartner->id, 404);
        $validated = $request->validate([
            'problem_title' => ['required', 'string', 'max:180'],
            'problem_statement' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(CommunityNeedPriority::CATEGORIES))],
            'priority_level' => ['required', Rule::in(CommunityNeedPriority::PRIORITY_LEVELS)],
            'sdg_codes' => ['required', 'array', 'min:1'],
            'sdg_codes.*' => [Rule::in(array_keys(CommunityNeedPriority::SDGS))],
            'location' => ['required', 'string', 'max:255'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'required_skills_text' => ['nullable', 'string', 'max:2000'],
            'recommended_response' => ['nullable', 'string', 'max:5000'],
        ]);
        $skills = collect(preg_split('/[,\r\n]+/', $validated['required_skills_text'] ?? '') ?: [])->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
        unset($validated['required_skills_text']);
        $assessment->priorities()->create($validated + ['required_skills' => $skills, 'status' => 'open']);

        return back()->with('status', 'Priority community problem added to the assessment.');
    }

    public function updatePriority(Request $request, CommunityNeedPriority $priority): RedirectResponse
    {
        $this->authorizeManager($request);
        $priority->update($request->validate(['status' => ['required', Rule::in(CommunityNeedPriority::STATUSES)]]));

        return back()->with('status', 'Community need status updated.');
    }

    private function partnerData(Request $request): array
    {
        $partner = $request->route('communityPartner');
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:180',
                Rule::unique('community_partners', 'name')
                    ->where('institution_id', app(InstitutionContext::class)->id())
                    ->ignore($partner?->id),
            ],
            'partner_type' => ['required', Rule::in(array_keys(CommunityPartner::TYPES))],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:2000'],
            'province' => ['nullable', 'string', 'max:120'],
            'city_municipality' => ['nullable', 'string', 'max:120'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'service_areas_text' => ['nullable', 'string', 'max:2000'],
            'available_resources_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        foreach (['service_areas', 'available_resources'] as $field) {
            $validated[$field] = collect(preg_split('/[,\r\n]+/', $validated[$field.'_text'] ?? '') ?: [])->map(fn ($value) => trim($value))->filter()->unique()->values()->all();
            unset($validated[$field.'_text']);
        }
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function visiblePartners(Request $request): Builder
    {
        $query = CommunityPartner::query();
        if (! $this->canManage($request)) {
            $query->where('verification_status', 'verified')->where('is_active', true);
        }

        return $query;
    }

    private function ensureVisible(Request $request, CommunityPartner $partner): void
    {
        abort_unless($this->visiblePartners($request)->whereKey($partner)->exists(), 404);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->isNstpAdmin() || $request->user()->isCoordinator();
    }

    private function context(Request $request): array
    {
        return [
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->prefix($request),
            'canManageRegistry' => $this->canManage($request),
            'partnerTypes' => CommunityPartner::TYPES,
            'needCategories' => CommunityNeedPriority::CATEGORIES,
            'sdgs' => CommunityNeedPriority::SDGS,
        ];
    }

    private function prefix(Request $request): string
    {
        return $this->access->routePrefix($request->user());
    }
}
