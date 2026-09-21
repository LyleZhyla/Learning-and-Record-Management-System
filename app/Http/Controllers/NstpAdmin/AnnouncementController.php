<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\NstpComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'audience' => ['nullable', Rule::in(array_keys(Announcement::AUDIENCES))],
        ]);
        $announcements = Announcement::with(['author', 'component'])
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('author_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['audience'] ?? null, fn ($query, $audience) => $query->where('audience', $audience))
            ->latest()->paginate(15)->withQueryString();

        return view('nstp_admin.announcements.index', $this->viewData($request) + compact('announcements', 'filters'));
    }

    public function create(Request $request): View
    {
        $this->authorizeCreator($request);

        return view('nstp_admin.announcements.create', $this->viewData($request) + $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCreator($request);
        $validated = $request->validate($this->rules());
        $announcement = Announcement::create($this->payload($validated, $request));

        return redirect()->route($this->routePrefix($request).'.announcements.edit', $announcement)
            ->with('status', 'Announcement created successfully.');
    }

    public function edit(Request $request, Announcement $announcement): View
    {
        $this->authorizeOwner($request, $announcement);

        return view('nstp_admin.announcements.edit', $this->viewData($request) + $this->formData($request) + compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorizeOwner($request, $announcement);
        $oldAttachment = $announcement->attachment_path;
        $validated = $request->validate($this->rules());
        $announcement->update($this->payload($validated, $request, $announcement));

        if ($oldAttachment && (($validated['remove_attachment'] ?? false) || $request->hasFile('attachment'))) {
            Storage::disk('local')->delete($oldAttachment);
        }

        return back()->with('status', 'Announcement updated successfully.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            $this->authorizeOwner($request, $announcement);
        }

        if ($announcement->attachment_path) {
            Storage::disk('local')->delete($announcement->attachment_path);
        }
        $announcement->delete();

        return redirect()->route($this->routePrefix($request).'.announcements.index')->with('status', 'Announcement deleted.');
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    private function payload(array $validated, Request $request, ?Announcement $announcement = null): array
    {
        if ($request->user()->isCoordinator()) {
            $validated['component_id'] = $request->user()->nstp_component_id;
        }

        unset($validated['attachment'], $validated['remove_attachment']);
        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('announcement-attachments');
            $validated['attachment_original_name'] = $request->file('attachment')->getClientOriginalName();
        } elseif ($request->boolean('remove_attachment')) {
            $validated['attachment_path'] = null;
            $validated['attachment_original_name'] = null;
        }

        return $validated + [
            'author_id' => $announcement?->author_id ?? $request->user()->id,
            'published_at' => $validated['status'] === 'published' ? ($announcement?->published_at ?? now()) : null,
        ];
    }

    private function formData(Request $request): array
    {
        return [
            'components' => NstpComponent::where('is_active', true)
                ->when($request->user()->isCoordinator(), fn ($query) => $query->whereKey($request->user()->nstp_component_id))
                ->orderBy('code')->get(),
            'audiences' => Announcement::AUDIENCES,
        ];
    }

    private function authorizeCreator(Request $request): void
    {
        abort_unless(
            $request->user()->isSuperAdmin()
            || $request->user()->isNstpAdmin()
            || $request->user()->isCoordinator(),
            403,
        );

        if ($request->user()->isCoordinator()) {
            abort_unless(
                NstpComponent::whereKey($request->user()->nstp_component_id)->where('is_active', true)->exists(),
                403,
                'An active NSTP component assignment is required to create announcements.',
            );
        }
    }

    private function authorizeOwner(Request $request, Announcement $announcement): void
    {
        $this->authorizeCreator($request);

        if ($request->user()->isSuperAdmin()) {
            return;
        }

        abort_unless($announcement->author_id === $request->user()->id, 403);
    }

    /** @return array{layout: string, routePrefix: string, canCreate: bool, showAuthor: bool} */
    private function viewData(Request $request): array
    {
        $prefix = $this->routePrefix($request);

        return [
            'layout' => match ($prefix) {
                'admin' => 'layouts.admin',
                'coordinator' => 'layouts.coordinator',
                default => 'layouts.nstp-admin',
            },
            'routePrefix' => $prefix,
            'canCreate' => true,
            'showAuthor' => $request->user()->isSuperAdmin(),
        ];
    }

    private function routePrefix(Request $request): string
    {
        return match (true) {
            $request->user()->isSuperAdmin() => 'admin',
            $request->user()->isCoordinator() => 'coordinator',
            default => 'nstp_admin',
        };
    }
}
