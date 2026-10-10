@extends($layout)

@section('title', 'Policies & Requirements')
@section('page-title', 'Policies & Requirements')

@section('content')
<section class="policy-config-hero">
    <div><span class="eyebrow">Configuration layer</span><h2>Set the institution-wide operating policies</h2><p>Manage the active academic term, availability windows, security timeout, grading defaults, and required-document enforcement from one screen.</p></div>
    <div><strong>6</strong><span>policy areas</span></div>
</section>

<form method="POST" action="{{ route($routePrefix.'.policies.update') }}" class="policy-config-form">
    @csrf
    @method('PUT')

    <div class="policy-config-grid">
        <section class="card policy-config-card">
            <header><span>01</span><div><h3>Registration opening</h3><p>Control whether the public student registration form accepts new applications.</p></div></header>
            <label class="field-group"><span>Registration status</span><select name="student_registration_open" required><option value="1" @selected(old('student_registration_open', $registrationOpen ? '1' : '0') === '1')>Open — accept applications</option><option value="0" @selected(old('student_registration_open', $registrationOpen ? '1' : '0') === '0')>Closed — stop applications</option></select></label>
        </section>

        <section class="card policy-config-card">
            <header><span>02</span><div><h3>Academic term</h3><p>Set the academic year and semester attached to new registrations and operational defaults.</p></div></header>
            <div class="form-grid"><label class="field-group"><span>Academic year</span><input name="student_registration_academic_year" value="{{ old('student_registration_academic_year', $registrationAcademicYear) }}" pattern="\d{4}-\d{4}" required>@error('student_registration_academic_year')<small class="field-error">{{ $message }}</small>@enderror</label><label class="field-group"><span>Semester</span><select name="student_registration_semester" required>@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected(old('student_registration_semester', $registrationSemester) === $value)>{{ $label }}</option>@endforeach</select></label></div>
        </section>

        <section class="card policy-config-card">
            <header><span>03</span><div><h3>Component selection</h3><p>Open or close CWTS, LTS, and ROTC selection for students who do not yet have a final enrollment.</p></div></header>
            <label class="field-group"><span>Selection status</span><select name="component_selection_open" required><option value="1" @selected(old('component_selection_open', $componentSelectionOpen ? '1' : '0') === '1')>Open — allow submissions</option><option value="0" @selected(old('component_selection_open', $componentSelectionOpen ? '1' : '0') === '0')>Closed — prevent submissions</option></select></label>
        </section>

        <section class="card policy-config-card">
            <header><span>04</span><div><h3>Inactivity timeout</h3><p>Automatically sign out any account after the configured period without a request.</p></div></header>
            <label class="field-group"><span>Inactive for</span><div class="policy-unit-input"><input type="number" name="inactivity_timeout_minutes" min="1" max="1440" value="{{ old('inactivity_timeout_minutes', $timeoutMinutes) }}" required><strong>minutes</strong></div></label>
        </section>

        <section class="card policy-config-card">
            <header><span>05</span><div><h3>Passing grade</h3><p>Define the grading defaults assigned when a new section receives its gradebook.</p></div></header>
            <div class="form-grid"><label class="field-group"><span>Passing percentage</span><input type="number" name="default_passing_percentage" min="1" max="99.99" step="0.01" value="{{ old('default_passing_percentage', $defaultPassingPercentage) }}" required></label><label class="field-group"><span>Passing grade</span><input type="number" name="default_passing_grade" min="1.01" max="4.99" step="0.01" value="{{ old('default_passing_grade', $defaultPassingGrade) }}" required></label></div>
            <label class="policy-check"><input type="hidden" name="apply_grading_policy_to_existing_sections" value="0"><input type="checkbox" name="apply_grading_policy_to_existing_sections" value="1"><span><strong>Apply to existing sections now</strong><small>Updates the passing percentage and passing grade while preserving their categories and other scale values.</small></span></label>
        </section>

        <section class="card policy-config-card">
            <header><span>06</span><div><h3>Required documents</h3><p>Control whether required uploads are treated as mandatory across student document screens and imported-account access.</p></div></header>
            <label class="field-group"><span>Requirement enforcement</span><select name="required_documents_enforced" required><option value="1" @selected(old('required_documents_enforced', $requiredDocumentsEnforced ? '1' : '0') === '1')>Enforced — required uploads remain mandatory</option><option value="0" @selected(old('required_documents_enforced', $requiredDocumentsEnforced ? '1' : '0') === '0')>Advisory — show uploads as optional</option></select></label>
            <div class="policy-document-summary"><strong>{{ $requiredDocumentCount }}</strong><span>active required document {{ Str::plural('definition', $requiredDocumentCount) }}</span><a href="{{ route($routePrefix.'.document-forms.index') }}">Manage document definitions →</a></div>
        </section>
    </div>

    <footer class="card policy-config-footer">
        <div><strong>Policy changes take effect immediately</strong><span>New sessions, registrations, component submissions, gradebooks, and document screens use the saved values. Last updated by {{ $lastUpdatedSetting?->updater?->name ?? 'System default' }}{{ $lastUpdatedSetting?->updated_at ? ' · '.$lastUpdatedSetting->updated_at->format('M j, Y g:i A') : '' }}.</span></div>
        <button class="primary-button" type="submit">Save policies &amp; requirements</button>
    </footer>
</form>
@endsection
