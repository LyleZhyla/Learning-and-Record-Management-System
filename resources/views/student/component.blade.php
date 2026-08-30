@extends('layouts.student')
@section('title', 'NSTP Selection')
@section('page-title', 'NSTP Selection')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">Student enrollment</span><h2>Choose your NSTP component</h2><p>Select your preferred component and required shirt size for {{ $semesterLabel }} {{ $academicYear }}.</p></div>
</section>

<div class="student-selection-layout">
    <section class="card student-component-card">
        <div class="card-heading"><div><h3>Enrollment preferences</h3><p>Both your NSTP component and shirt size are required.</p></div></div>
        <form method="POST" action="{{ route('student.component.update') }}" class="settings-form">
            @csrf
            @method('PUT')

            <fieldset class="component-choice-fieldset">
                <legend>NSTP component</legend>
                <div class="component-choice-grid">
                    @foreach($availableComponents as $component)
                        <label class="component-choice-card">
                            <input type="radio" name="nstp_component_id" value="{{ $component->id }}" @checked((int) old('nstp_component_id', $currentEnrollment?->component_id) === $component->id) required>
                            <span class="component-choice-mark">{{ substr($component->code, 0, 1) }}</span>
                            <strong>{{ $component->code }}</strong>
                            <small>{{ $component->name }}</small>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('nstp_component_id')<small class="field-error">{{ $message }}</small>@enderror

            <label for="shirt_size">Shirt size</label>
            <select id="shirt_size" name="shirt_size" required>
                <option value="">Choose your shirt size</option>
                @foreach($shirtSizes as $value => $label)
                    <option value="{{ $value }}" @selected(old('shirt_size', $currentEnrollment?->shirt_size) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('shirt_size')<small class="field-error">{{ $message }}</small>@enderror

            <p class="form-help">If you change components, your current section assignment will be cleared and the NSTP Admin will assign a new section.</p>
            <div class="form-actions"><button class="primary-button compact" type="submit">Save enrollment preferences</button></div>
        </form>
    </section>

    <aside class="card student-selection-summary">
        <div class="card-heading"><div><span class="eyebrow">Current selection</span><h3>Enrollment summary</h3></div></div>
        <dl>
            <div><dt>Component</dt><dd>{{ $currentEnrollment?->component?->code ?? 'Not selected' }}</dd></div>
            <div><dt>Shirt size</dt><dd>{{ $currentEnrollment?->shirt_size ?? 'Not selected' }}</dd></div>
            <div><dt>Section</dt><dd>{{ $currentEnrollment?->section?->code ?? 'Awaiting assignment' }}</dd></div>
            <div><dt>Term</dt><dd>{{ $semesterLabel }} {{ $academicYear }}</dd></div>
        </dl>
    </aside>
</div>
@endsection
