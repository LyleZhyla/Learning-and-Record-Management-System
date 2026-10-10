<?php

namespace App\Http\Controllers;

use App\Models\DocumentForm;
use App\Models\DocumentSubmission;
use App\Models\NstpComponent;
use App\Models\ReviewCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReviewController extends Controller
{
    public function index(Request $request): View
    {
        $statusLabels = ReviewCategory::labels('document_submission');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys($statusLabels))],
            'document_form_id' => ['nullable', 'integer', 'exists:document_forms,id'],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $submissions = DocumentSubmission::with(['documentForm.component', 'user.studentProfile', 'enrollment.component', 'reviewer'])
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['document_form_id'] ?? null, fn ($query, $value) => $query->where('document_form_id', $value))
            ->when($filters['component_id'] ?? null, fn ($query, $value) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('component_id', $value)))
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->whereHas('user', fn ($user) => $user->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")))
            ->latest()->paginate(15)->withQueryString();
        $prefix = $this->prefix($request);

        return view('admin.document-reviews.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'submissions' => $submissions,
            'forms' => DocumentForm::orderBy('title')->get(),
            'components' => NstpComponent::orderBy('code')->get(),
            'statuses' => $statusLabels,
            'reviewCategories' => ReviewCategory::categories('document_submission', true),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, DocumentSubmission $documentSubmission): RedirectResponse
    {
        $activeCategories = ReviewCategory::categories('document_submission', true)->keyBy('slug');
        $selectedOutcome = $activeCategories->get($request->input('status'))?->outcome ?? 'pending';
        $validated = $request->validate([
            'status' => ['required', Rule::in($activeCategories->keys()->all())],
            'review_notes' => [Rule::requiredIf($selectedOutcome === 'correction'), 'nullable', 'string', 'max:2000'],
        ]);
        $documentSubmission->update($validated + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('status', 'The document review was saved.');
    }

    public function file(DocumentSubmission $documentSubmission): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($documentSubmission->file_path), 404);

        return Storage::disk('local')->response($documentSubmission->file_path, $documentSubmission->original_filename, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function download(DocumentSubmission $documentSubmission): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($documentSubmission->file_path), 404);

        return Storage::disk('local')->download($documentSubmission->file_path, $documentSubmission->original_filename);
    }

    private function prefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
