@extends($layout)

@section('title', $documentForm ? 'Edit Document/Form' : 'Create Document/Form')
@section('page-title', $documentForm ? 'Edit Document/Form' : 'Create Document/Form')

@section('content')
<div class="back-row"><a href="{{ route($routePrefix.'.document-forms.index') }}">← Back to documents and forms</a></div>
<form method="POST" enctype="multipart/form-data" action="{{ $documentForm ? route($routePrefix.'.document-forms.update', $documentForm) : route($routePrefix.'.document-forms.store') }}" class="document-builder" data-document-builder>
    @csrf @if($documentForm) @method('PUT') @endif
    <section class="card">
        <div class="card-heading"><div><span class="eyebrow">Definition</span><h3>Document/form details</h3><p>Configure who sees this item, what they can download, and what they must submit.</p></div></div>
        <div class="form-grid">
            <label class="field-group"><span>Title</span><input name="title" maxlength="150" value="{{ old('title', $documentForm?->title) }}" required></label>
            <label class="field-group"><span>Type</span><select name="category" required data-document-category>@foreach($categories as $value=>$label)<option value="{{ $value }}" @selected(old('category', $documentForm?->category ?? 'document') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group full"><span>Description</span><textarea name="description" rows="3" maxlength="1000">{{ old('description', $documentForm?->description) }}</textarea></label>
            <label class="field-group full"><span>Student instructions</span><textarea name="instructions" rows="4" maxlength="2000">{{ old('instructions', $documentForm?->instructions) }}</textarea></label>
            <label class="field-group"><span>Audience</span><select name="component_id"><option value="">All NSTP students</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected((int)old('component_id', $documentForm?->component_id) === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
            <label class="field-group"><span>Status</span><select name="is_active" required><option value="1" @selected((string)old('is_active', $documentForm?->is_active ?? 1)==='1')>Active</option><option value="0" @selected((string)old('is_active', $documentForm?->is_active ?? 1)==='0')>Inactive</option></select></label>
            <label class="field-group"><span>Opens at (optional)</span><input type="datetime-local" name="opens_at" value="{{ old('opens_at', $documentForm?->opens_at?->format('Y-m-d\TH:i')) }}"></label>
            <label class="field-group"><span>Closes at (optional)</span><input type="datetime-local" name="closes_at" value="{{ old('closes_at', $documentForm?->closes_at?->format('Y-m-d\TH:i')) }}"></label>
            <label class="field-group"><span>Display order</span><input type="number" name="sort_order" min="0" max="999" value="{{ old('sort_order', $documentForm?->sort_order ?? 0) }}" required></label>
        </div>
    </section>

    <section class="card">
        <div class="card-heading"><div><span class="eyebrow">Template and response</span><h3>Download and upload behavior</h3></div></div>
        <div class="form-grid">
            <label class="field-group full"><span>Template file {{ $documentForm?->template_path ? '(replace existing)' : '(optional for documents, required for forms)' }}</span><input type="file" name="template" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">@if($documentForm?->template_path)<small class="form-help">Current: <a class="text-link" href="{{ route($routePrefix.'.document-forms.template', $documentForm) }}">{{ $documentForm->template_original_name }}</a></small>@endif</label>
            <label class="field-group"><span>Student response</span><select name="requires_submission" required data-requires-submission><option value="1" @selected((string)old('requires_submission', $documentForm?->requires_submission ?? 1)==='1')>Upload required/allowed</option><option value="0" @selected((string)old('requires_submission', $documentForm?->requires_submission ?? 1)==='0')>Download/information only</option></select></label>
            <label class="field-group" data-required-setting><span>Requirement level</span><select name="is_required" required><option value="1" @selected((string)old('is_required', $documentForm?->is_required ?? 1)==='1')>Required</option><option value="0" @selected((string)old('is_required', $documentForm?->is_required ?? 1)==='0')>Optional</option></select></label>
            <fieldset class="field-group full extension-picker" data-upload-setting><legend>Accepted student upload types</legend><div>@foreach($extensionOptions as $extension)<label><input type="checkbox" name="accepted_extensions[]" value="{{ $extension }}" @checked(collect(old('accepted_extensions', $documentForm?->accepted_extensions ?? ['pdf']))->contains($extension))> {{ strtoupper($extension) }}</label>@endforeach</div></fieldset>
            <label class="field-group" data-upload-setting><span>Maximum upload size</span><select name="max_size_mb" required>@foreach([1,2,5,10,15,25] as $size)<option value="{{ $size }}" @selected((int)old('max_size_mb', $documentForm ? $documentForm->max_size_kb/1024 : 5)===$size)>{{ $size }} MB</option>@endforeach</select></label>
        </div>
    </section>
    <div class="form-actions split-actions">@if($documentForm)<button class="danger-button" type="submit" form="delete-document-form">Delete</button>@else<span></span>@endif<div><a class="cancel-button" href="{{ route($routePrefix.'.document-forms.index') }}">Cancel</a> <button class="primary-button compact" type="submit">Save configuration</button></div></div>
</form>
@if($documentForm)<form id="delete-document-form" method="POST" action="{{ route($routePrefix.'.document-forms.destroy', $documentForm) }}" onsubmit="return confirm('Delete this configuration?');">@csrf @method('DELETE')</form>@endif
<script src="{{ asset('js/document-form-builder.js') }}?v={{ filemtime(public_path('js/document-form-builder.js')) }}"></script>
@endsection
