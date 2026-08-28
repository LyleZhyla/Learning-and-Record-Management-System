<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaterialController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $sectionIds = $this->access->manageableSections($request->user())->pluck('id');
        $componentIds = NstpSection::whereIn('id', $sectionIds)->pluck('component_id')->unique();
        $materials = LearningMaterial::with(['component', 'section', 'creator'])
            ->where(function ($query) use ($sectionIds, $componentIds) {
                $query->whereIn('section_id', $sectionIds)
                    ->orWhere(fn ($q) => $q->whereNull('section_id')->whereIn('component_id', $componentIds));
            })
            ->latest()->paginate(15);

        return view('learning.materials.index', $this->context($request) + compact('materials'));
    }

    public function create(Request $request): View
    {
        return view('learning.materials.create', $this->context($request) + [
            'components' => NstpComponent::where('is_active', true)->orderBy('code')->get(),
            'sections' => $this->access->manageableSections($request->user())->with('component')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'component_id' => ['required', 'exists:nstp_components,id'],
            'section_id' => ['nullable', 'exists:nstp_sections,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt', 'max:10240', 'required_without:external_url'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_without:file'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        if ($validated['section_id'] ?? null) {
            $section = NstpSection::findOrFail($validated['section_id']);
            $this->access->ensureCanManageSection($request->user(), $section);
            if ((int) $section->component_id !== (int) $validated['component_id']) {
                throw ValidationException::withMessages(['section_id' => 'The selected section does not belong to this component.']);
            }
        } elseif ($request->user()->isFacilitator()) {
            throw ValidationException::withMessages(['section_id' => 'Facilitators must select one of their assigned sections.']);
        }

        $file = $request->file('file');
        LearningMaterial::create([
            ...collect($validated)->except('file')->all(),
            'created_by' => $request->user()->id,
            'file_path' => $file?->store('learning-materials'),
            'original_filename' => $file?->getClientOriginalName(),
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        return redirect()->route($this->access->routePrefix($request->user()).'.materials.index')
            ->with('status', 'Learning material saved successfully.');
    }

    public function download(Request $request, LearningMaterial $material): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeMaterial($request, $material);
        abort_unless($material->file_path && Storage::exists($material->file_path), 404);

        return response()->download(Storage::path($material->file_path), $material->original_filename);
    }

    private function authorizeMaterial(Request $request, LearningMaterial $material): void
    {
        $user = $request->user();
        if ($user->isStudent()) {
            $enrollment = $this->access->currentEnrollment($user);
            abort_unless($enrollment && $material->status === 'published' && $material->component_id === $enrollment->component_id
                && (! $material->section_id || $material->section_id === $enrollment->section_id), 403);
            return;
        }

        if ($material->section) {
            $this->access->ensureCanManageSection($user, $material->section);
        } else {
            abort_unless($user->isSuperAdmin() || $user->isNstpAdmin(), 403);
        }
    }

    private function context(Request $request): array
    {
        return ['layout' => $this->access->layout($request->user()), 'routePrefix' => $this->access->routePrefix($request->user())];
    }
}
