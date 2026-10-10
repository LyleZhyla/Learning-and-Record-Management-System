<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\NstpComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentFormController extends Controller
{
    public function index(Request $request): View
    {
        $forms = DocumentForm::with('component')->withCount('submissions')->orderBy('sort_order')->orderBy('title')->get();

        return view('admin.document-forms.index', $this->viewData($request) + compact('forms'));
    }

    public function create(Request $request): View
    {
        return view('admin.document-forms.form', $this->viewData($request) + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $template = $request->file('template');
        $validated['slug'] = DocumentForm::uniqueSlug($validated['title']);
        $validated['max_size_kb'] = (int) $validated['max_size_mb'] * 1024;
        unset($validated['max_size_mb'], $validated['template']);

        $newTemplatePath = null;
        if ($template) {
            $newTemplatePath = $template->store('document-form-templates', 'local');
            $validated['template_path'] = $newTemplatePath;
            $validated['template_original_name'] = $template->getClientOriginalName();
        }
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;
        try {
            $form = DocumentForm::create($validated);
        } catch (Throwable $exception) {
            if ($newTemplatePath) {
                Storage::disk('local')->delete($newTemplatePath);
            }
            throw $exception;
        }

        return redirect()->route($this->prefix($request).'.document-forms.edit', $form)->with('status', 'The document/form configuration was created.');
    }

    public function edit(Request $request, DocumentForm $documentForm): View
    {
        return view('admin.document-forms.form', $this->viewData($request) + $this->formData($documentForm));
    }

    public function update(Request $request, DocumentForm $documentForm): RedirectResponse
    {
        $validated = $this->validated($request, $documentForm);
        $template = $request->file('template');
        $oldTemplate = $documentForm->template_path;
        $validated['slug'] = DocumentForm::uniqueSlug($validated['title'], $documentForm->id);
        $validated['max_size_kb'] = (int) $validated['max_size_mb'] * 1024;
        unset($validated['max_size_mb'], $validated['template']);

        $newTemplatePath = null;
        if ($template) {
            $newTemplatePath = $template->store('document-form-templates', 'local');
            $validated['template_path'] = $newTemplatePath;
            $validated['template_original_name'] = $template->getClientOriginalName();
        }
        $validated['updated_by'] = $request->user()->id;
        try {
            $documentForm->update($validated);
        } catch (Throwable $exception) {
            if ($newTemplatePath) {
                Storage::disk('local')->delete($newTemplatePath);
            }
            throw $exception;
        }

        if ($template && $oldTemplate && $oldTemplate !== $documentForm->template_path) {
            Storage::disk('local')->delete($oldTemplate);
        }

        return back()->with('status', 'The document/form configuration was updated.');
    }

    public function destroy(Request $request, DocumentForm $documentForm): RedirectResponse
    {
        if ($documentForm->submissions()->exists()) {
            throw ValidationException::withMessages(['document_form' => 'Deactivate this item instead. Configurations with submissions cannot be deleted.']);
        }

        if ($documentForm->template_path) {
            Storage::disk('local')->delete($documentForm->template_path);
        }
        $documentForm->delete();

        return redirect()->route($this->prefix($request).'.document-forms.index')->with('status', 'The document/form configuration was deleted.');
    }

    public function downloadTemplate(Request $request, DocumentForm $documentForm): StreamedResponse
    {
        abort_unless($documentForm->template_path && Storage::disk('local')->exists($documentForm->template_path), 404);

        return Storage::disk('local')->download($documentForm->template_path, $documentForm->template_original_name ?: $documentForm->slug);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?DocumentForm $form = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(DocumentForm::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'accepted_extensions' => ['nullable', 'array'],
            'accepted_extensions.*' => [Rule::in(DocumentForm::ALLOWED_EXTENSIONS)],
            'max_size_mb' => ['required', 'integer', 'min:1', 'max:25'],
            'requires_submission' => ['required', 'boolean'],
            'is_required' => ['required', 'boolean'],
            'template' => ['nullable', 'file', 'mimes:'.implode(',', DocumentForm::ALLOWED_EXTENSIONS), 'max:25600'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $validated['accepted_extensions'] = array_values(array_unique($validated['accepted_extensions'] ?? ['pdf']));
        if ($request->boolean('requires_submission') && $validated['accepted_extensions'] === []) {
            throw ValidationException::withMessages(['accepted_extensions' => 'Select at least one accepted upload type.']);
        }
        if ($validated['category'] === 'form' && ! $request->hasFile('template') && ! $form?->template_path) {
            throw ValidationException::withMessages(['template' => 'Upload the downloadable form template.']);
        }
        if (! $request->boolean('requires_submission')) {
            $validated['is_required'] = false;
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function formData(?DocumentForm $form = null): array
    {
        return [
            'documentForm' => $form,
            'components' => NstpComponent::orderBy('code')->get(),
            'categories' => DocumentForm::CATEGORIES,
            'extensionOptions' => DocumentForm::ALLOWED_EXTENSIONS,
        ];
    }

    /** @return array<string, string> */
    private function viewData(Request $request): array
    {
        $prefix = $this->prefix($request);

        return ['layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin', 'routePrefix' => $prefix];
    }

    private function prefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
