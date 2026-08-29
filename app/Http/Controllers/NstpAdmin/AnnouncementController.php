<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\NstpComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['audience'] ?? null, fn ($query, $audience) => $query->where('audience', $audience))
            ->latest()->paginate(15)->withQueryString();

        return view('nstp_admin.announcements.index', compact('announcements', 'filters'));
    }

    public function create(): View
    {
        return view('nstp_admin.announcements.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $announcement = Announcement::create($this->payload($validated, $request));

        return redirect()->route('nstp_admin.announcements.edit', $announcement)
            ->with('status', 'Announcement created successfully.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('nstp_admin.announcements.edit', $this->formData() + compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->payload($request->validate($this->rules()), $request, $announcement));

        return back()->with('status', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return redirect()->route('nstp_admin.announcements.index')->with('status', 'Announcement deleted.');
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    private function payload(array $validated, Request $request, ?Announcement $announcement = null): array
    {
        return $validated + [
            'author_id' => $announcement?->author_id ?? $request->user()->id,
            'published_at' => $validated['status'] === 'published' ? ($announcement?->published_at ?? now()) : null,
        ];
    }

    private function formData(): array
    {
        return [
            'components' => NstpComponent::where('is_active', true)->orderBy('code')->get(),
            'audiences' => Announcement::AUDIENCES,
        ];
    }
}
