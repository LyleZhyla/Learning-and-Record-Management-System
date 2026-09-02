@extends('layouts.student')
@section('title', 'NSTP Selection')
@section('page-title', 'NSTP Selection')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">Student enrollment</span><h2>{{ $currentEnrollment ? 'Your NSTP component' : 'Choose your NSTP component' }}</h2><p>{{ $currentEnrollment ? 'Your component selection for this term is final. You may still update the supporting enrollment details below.' : 'Select your preferred component and required shirt size for '.$semesterLabel.' '.$academicYear.'.' }}</p></div>
</section>

<div class="student-selection-layout">
    <section class="card student-component-card">
        <div class="card-heading"><div><h3>Enrollment preferences</h3><p>Both your NSTP component and shirt size are required.</p></div></div>
        <form method="POST" action="{{ route('student.component.update') }}" class="settings-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <fieldset class="component-choice-fieldset">
                <legend>NSTP component @if($currentEnrollment)<span class="component-final-badge">Final selection</span>@endif</legend>
                <div class="component-choice-grid">
                    @foreach($availableComponents as $component)
                        <label class="component-choice-card @if($currentEnrollment) component-choice-locked @endif">
                            <input type="radio" @if(! $currentEnrollment) name="nstp_component_id" @endif value="{{ $component->id }}" data-component-code="{{ $component->code }}" @checked((int) old('nstp_component_id', $currentEnrollment?->component_id) === $component->id) @disabled($currentEnrollment) required>
                            <span class="component-choice-mark">{{ substr($component->code, 0, 1) }}</span>
                            <strong>{{ $component->code }}</strong>
                            <small>{{ $component->name }}</small>
                        </label>
                    @endforeach
                </div>
                @if($currentEnrollment)<input type="hidden" name="nstp_component_id" value="{{ $currentEnrollment->component_id }}"><p class="component-lock-note">This component can no longer be changed after selection.</p>@endif
            </fieldset>
            @error('nstp_component_id')<small class="field-error">{{ $message }}</small>@enderror

            <div class="rotc-category-panel" data-rotc-category-panel @if(old('rotc_category', $currentEnrollment?->rotc_category) || $currentEnrollment?->component?->code === 'ROTC') data-initially-visible @endif>
                <label for="rotc_category">ROTC category</label>
                <select id="rotc_category" name="rotc_category" data-rotc-category-select>
                    <option value="">Choose MS-1, MS-31, or MS-41</option>
                    @foreach($rotcCategories as $value => $label)
                        <option value="{{ $value }}" @selected(old('rotc_category', $currentEnrollment?->rotc_category) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('rotc_category')<small class="field-error">{{ $message }}</small>@enderror
            </div>

            <div class="rotc-proof-panel" data-rotc-proof-panel data-has-existing-proof="{{ $currentEnrollment?->rotc_proof_path ? 'true' : 'false' }}" hidden>
                <label for="ms1_proof">Proof of completed MS-1</label>
                <input id="ms1_proof" name="ms1_proof" type="file" accept=".pdf,.jpg,.jpeg,.png" data-rotc-proof-input>
                <small>Required for MS-31 and MS-41. Upload a PDF, JPG, or PNG up to 5 MB.</small>
                @if($currentEnrollment?->rotc_proof_path)<small class="existing-proof-note">Existing proof: {{ $currentEnrollment->rotc_proof_original_name }}</small>@endif
                @error('ms1_proof')<small class="field-error">{{ $message }}</small>@enderror
            </div>

            <label for="shirt_size">Shirt size</label>
            <select id="shirt_size" name="shirt_size" required>
                <option value="">Choose your shirt size</option>
                @foreach($shirtSizes as $value => $label)
                    <option value="{{ $value }}" @selected(old('shirt_size', $currentEnrollment?->shirt_size) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('shirt_size')<small class="field-error">{{ $message }}</small>@enderror

            <p class="form-help">{{ $currentEnrollment ? 'Only supporting details can be updated. Contact the NSTP Admin if the recorded component needs administrative correction.' : 'Review your choice carefully. The component cannot be changed after the first successful submission.' }}</p>
            <div class="form-actions"><button class="primary-button compact" type="submit">{{ $currentEnrollment ? 'Update enrollment details' : 'Save enrollment preferences' }}</button></div>
        </form>
    </section>

    <aside class="card student-selection-summary">
        <div class="card-heading"><div><span class="eyebrow">Current selection</span><h3>Enrollment summary</h3></div></div>
        <dl>
            <div><dt>Component</dt><dd>{{ $currentEnrollment?->component?->code ?? 'Not selected' }}</dd></div>
            @if($currentEnrollment?->component?->code === 'ROTC')<div><dt>ROTC category</dt><dd>{{ $currentEnrollment->rotc_category ?? 'Not selected' }}</dd></div>@endif
            @if(in_array($currentEnrollment?->rotc_category, ['MS-31', 'MS-41'], true))<div><dt>Approval</dt><dd>{{ $currentEnrollment->rotc_approval_status === 'approved' ? 'Approved' : 'Pending coordinator approval' }}</dd></div>@endif
            <div><dt>Shirt size</dt><dd>{{ $currentEnrollment?->shirt_size ?? 'Not selected' }}</dd></div>
            <div><dt>Section</dt><dd>{{ $currentEnrollment?->section?->code ?? 'Awaiting assignment' }}</dd></div>
            <div><dt>Term</dt><dd>{{ $semesterLabel }} {{ $academicYear }}</dd></div>
        </dl>
    </aside>
</div>

<script>
    const componentOptions = [...document.querySelectorAll('[data-component-code]')];
    const rotcCategoryPanel = document.querySelector('[data-rotc-category-panel]');
    const rotcCategorySelect = document.querySelector('[data-rotc-category-select]');
    const rotcProofPanel = document.querySelector('[data-rotc-proof-panel]');
    const rotcProofInput = document.querySelector('[data-rotc-proof-input]');

    function updateRotcCategory() {
        const selectedComponent = componentOptions.find((option) => option.checked);
        const isRotc = selectedComponent?.dataset.componentCode === 'ROTC';
        rotcCategoryPanel.hidden = !isRotc;
        rotcCategorySelect.required = isRotc;
        if (!isRotc) rotcCategorySelect.value = '';
        updateRotcProof();
    }

    function updateRotcProof() {
        const selectedComponent = componentOptions.find((option) => option.checked);
        const isAdvancedRotc = selectedComponent?.dataset.componentCode === 'ROTC'
            && ['MS-31', 'MS-41'].includes(rotcCategorySelect.value);
        const hasExistingProof = rotcProofPanel.dataset.hasExistingProof === 'true';
        rotcProofPanel.hidden = !isAdvancedRotc;
        rotcProofInput.required = isAdvancedRotc && !hasExistingProof;
        if (!isAdvancedRotc) rotcProofInput.value = '';
    }

    componentOptions.forEach((option) => option.addEventListener('change', updateRotcCategory));
    rotcCategorySelect.addEventListener('change', updateRotcProof);
    updateRotcCategory();
</script>
@endsection
