<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentSubmission;
use App\Models\ReviewCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $enrollment = $request->user()->latestNstpEnrollment()->with('component')->first();
        $forms = DocumentForm::available()->with('component')->orderBy('sort_order')->orderBy('title')->get()
            ->filter(fn (DocumentForm $form): bool => $form->appliesTo($enrollment))->values();
        $submissions = DocumentSubmission::where('user_id', $request->user()->id)
            ->whereIn('document_form_id', $forms->pluck('id'))
            ->where('academic_year', $enrollment?->academic_year)
            ->where('semester', $enrollment?->semester)
            ->get()->keyBy('document_form_id');

        return view('student.documents.index', compact('forms', 'submissions', 'enrollment'));
    }

    public function store(Request $request, DocumentForm $documentForm): RedirectResponse
    {
        $enrollment = $request->user()->latestNstpEnrollment()->first();
        abort_unless($documentForm->is_active && $documentForm->requires_submission && $documentForm->appliesTo($enrollment), 404);
        abort_if($documentForm->opens_at?->isFuture() || $documentForm->closes_at?->isPast(), 422, 'This submission window is closed.');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $documentForm->accepted_extensions ?? ['pdf']), 'max:'.$documentForm->max_size_kb],
        ]);
        $existing = DocumentSubmission::query()->where('document_form_id', $documentForm->id)
            ->where('user_id', $request->user()->id)
            ->where('academic_year', $enrollment?->academic_year)
            ->where('semester', $enrollment?->semester)->first();
        abort_if($existing && ReviewCategory::outcomeFor('document_submission', $existing->status) === 'approved', 422, 'A verified document cannot be replaced. Contact the NSTP Office if a correction is needed.');

        $file = $validated['file'];
        $newPath = $file->store('configurable-document-submissions', 'local');
        $oldPath = $existing?->file_path;

        try {
            $submission = $existing ?? new DocumentSubmission;
            $submission->fill([
                'document_form_id' => $documentForm->id,
                'user_id' => $request->user()->id,
                'enrollment_id' => $enrollment?->id,
                'academic_year' => $enrollment?->academic_year,
                'semester' => $enrollment?->semester,
                'file_path' => $newPath,
                'original_filename' => $file->getClientOriginalName(),
                'status' => ReviewCategory::defaultSlug('document_submission', 'pending', 'pending'),
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
            $submission->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', $documentForm->title.' was submitted for review.');
    }

    public function downloadTemplate(Request $request, DocumentForm $documentForm): StreamedResponse
    {
        $enrollment = $request->user()->latestNstpEnrollment()->first();
        abort_unless($documentForm->is_active && $documentForm->appliesTo($enrollment), 404);
        abort_unless($documentForm->template_path && Storage::disk('local')->exists($documentForm->template_path), 404);

        return Storage::disk('local')->download($documentForm->template_path, $documentForm->template_original_name ?: $documentForm->slug);
    }

    public function downloadSubmission(Request $request, DocumentSubmission $documentSubmission): StreamedResponse
    {
        abort_unless($documentSubmission->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($documentSubmission->file_path), 404);

        return Storage::disk('local')->download($documentSubmission->file_path, $documentSubmission->original_filename);
    }
}
