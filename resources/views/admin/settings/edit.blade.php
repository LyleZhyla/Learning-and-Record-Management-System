@extends('layouts.admin')
@section('title', 'System & Branding')
@section('page-title', 'System & Branding')

@section('content')
<div class="page-actions settings-page-heading">
    <div><span class="eyebrow">System configuration</span><h2>Manage one area at a time</h2><p>Branding, ID design, registration, and security now have separate controls and save actions.</p></div>
</div>

<nav class="settings-section-nav" aria-label="System settings sections">
    <a href="#branding-colors"><span>01</span><strong>Branding & Colors</strong><small>Names and system palette</small></a>
    <a href="#student-id"><span>02</span><strong>Student ID</strong><small>Card appearance and fields</small></a>
    <a href="#registration"><span>03</span><strong>Registration</strong><small>Active enrollment cycle</small></a>
    <a href="#security"><span>04</span><strong>Security</strong><small>Automatic logout policy</small></a>
</nav>

<div class="settings-section-stack">
    <section class="card settings-section-card" id="branding-colors">
        <div class="settings-section-heading"><span class="settings-section-number">01</span><div><span class="eyebrow">Brand identity</span><h3>Branding & system colors</h3><p>Update the institution text and the colors shared by every account portal.</p></div></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="branding">
            <div class="form-grid">
                <label class="field-group full"><span>University / organization name</span><input name="organization_name" value="{{ old('settings_section') === 'branding' ? old('organization_name') : $branding['organization_name'] }}" maxlength="150" required></label>
                <label class="field-group"><span>Organization short name</span><input name="organization_short_name" value="{{ old('settings_section') === 'branding' ? old('organization_short_name') : $branding['organization_short_name'] }}" maxlength="20" required></label>
                <label class="field-group"><span>Program short name</span><input name="program_short_name" value="{{ old('settings_section') === 'branding' ? old('program_short_name') : $branding['program_short_name'] }}" maxlength="20" required></label>
                <label class="field-group full"><span>Program name</span><input name="program_name" value="{{ old('settings_section') === 'branding' ? old('program_name') : $branding['program_name'] }}" maxlength="150" required></label>
                <label class="field-group full"><span>Platform name</span><input name="platform_name" value="{{ old('settings_section') === 'branding' ? old('platform_name') : $branding['platform_name'] }}" maxlength="180" required></label>
                <label class="field-group full"><span>Official website</span><input type="url" name="organization_website" value="{{ old('settings_section') === 'branding' ? old('organization_website') : $branding['organization_website'] }}" maxlength="255" placeholder="https://example.edu.ph"></label>
            </div>

            <div class="system-color-editor">
                <div><strong>System color palette</strong><p>These colors are applied to navigation, buttons, links, highlights, and status accents.</p></div>
                <div class="system-color-grid">
                    @foreach([
                        'system_primary_color' => ['Primary', 'Buttons and active controls', $systemColors['primary_color']],
                        'system_secondary_color' => ['Secondary', 'Sidebar and deep surfaces', $systemColors['secondary_color']],
                        'system_accent_color' => ['Accent', 'Success and highlights', $systemColors['accent_color']],
                    ] as $field => [$label, $help, $value])
                        <label class="system-color-field"><input type="color" name="{{ $field }}" value="{{ old('settings_section') === 'branding' ? old($field, $value) : $value }}" required><span><strong>{{ $label }}</strong><small>{{ $help }}</small></span></label>
                    @endforeach
                </div>
                <div class="system-color-preview" style="--system-preview-primary:{{ old('system_primary_color', $systemColors['primary_color']) }};--system-preview-secondary:{{ old('system_secondary_color', $systemColors['secondary_color']) }};--system-preview-accent:{{ old('system_accent_color', $systemColors['accent_color']) }}" data-system-color-preview><span></span><strong>System palette preview</strong><button type="button">Primary action</button><i>Accent</i></div>
            </div>

            <aside class="protected-brand-assets"><span aria-hidden="true">◆</span><div><strong>Protected brand assets</strong><p>SNAPIE characters, the official system logo lockup, and copyright text are fixed and cannot be replaced from this page.</p></div></aside>
            <div class="form-actions"><button class="primary-button compact">Save branding & colors</button></div>
        </form>
    </section>

    <section class="card settings-section-card" id="student-id">
        <div class="settings-section-heading"><span class="settings-section-number">02</span><div><span class="eyebrow">Student identity</span><h3>Student ID card design</h3><p>Customize the official ID without changing the system-wide brand assets.</p></div></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="student_id">
            <div class="form-grid">
                <label class="field-group full"><span>Card title</span><input name="student_id_title" value="{{ old('settings_section') === 'student_id' ? old('student_id_title') : $studentIdDesign['title'] }}" maxlength="40" required></label>
                <label class="field-group full"><span>Footer text</span><input name="student_id_footer_text" value="{{ old('settings_section') === 'student_id' ? old('student_id_footer_text') : $studentIdDesign['footer_text'] }}" maxlength="120" required></label>
                <label class="field-group id-design-color-field"><span>Primary color</span><input type="color" name="student_id_primary_color" value="{{ old('student_id_primary_color', $studentIdDesign['primary_color']) }}" required><small>Header, name, and footer</small></label>
                <label class="field-group id-design-color-field"><span>Secondary color</span><input type="color" name="student_id_secondary_color" value="{{ old('student_id_secondary_color', $studentIdDesign['secondary_color']) }}" required><small>Photo and supporting accents</small></label>
                <label class="field-group id-design-color-field"><span>Accent color</span><input type="color" name="student_id_accent_color" value="{{ old('student_id_accent_color', $studentIdDesign['accent_color']) }}" required><small>Top stripe and highlights</small></label>
            </div>
            <fieldset class="id-design-field-visibility">
                <legend>Information shown on the ID</legend>
                @foreach(['student_id_show_course' => ['Course', $studentIdDesign['show_course']], 'student_id_show_component' => ['NSTP component', $studentIdDesign['show_component']], 'student_id_show_emergency_contact' => ['Emergency contact', $studentIdDesign['show_emergency_contact']]] as $field => [$label, $enabled])
                    <label><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked(old('settings_section') === 'student_id' ? old($field) : $enabled)> <span>{{ $label }}</span></label>
                @endforeach
            </fieldset>
            <div class="id-design-color-preview" style="--preview-primary:{{ old('student_id_primary_color', $studentIdDesign['primary_color']) }};--preview-secondary:{{ old('student_id_secondary_color', $studentIdDesign['secondary_color']) }};--preview-accent:{{ old('student_id_accent_color', $studentIdDesign['accent_color']) }}" data-id-design-preview><i></i><strong data-id-preview-title>{{ old('student_id_title', $studentIdDesign['title']) }}</strong><span></span><small data-id-preview-footer>{{ old('student_id_footer_text', $studentIdDesign['footer_text']) }}</small></div>
            <div class="form-actions"><button class="primary-button compact">Save student ID design</button></div>
        </form>
    </section>

    <section class="card settings-section-card" id="registration">
        <div class="settings-section-heading"><span class="settings-section-number">03</span><div><span class="eyebrow">Enrollment window</span><h3>Student registration cycle</h3><p>Choose the period assigned to new public registrations.</p></div></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="registration">
            <label class="field-group"><span>Registration status</span><select name="student_registration_open" required><option value="1" @selected(old('student_registration_open', $registrationOpen ? '1' : '0') === '1')>Open — accept registrations</option><option value="0" @selected(old('student_registration_open', $registrationOpen ? '1' : '0') === '0')>Closed — stop registrations</option></select></label>
            <div class="form-grid">
                <label class="field-group"><span>Academic year</span><input name="student_registration_academic_year" value="{{ old('student_registration_academic_year', $registrationAcademicYear) }}" pattern="\d{4}-\d{4}" placeholder="2026-2027" required>@error('student_registration_academic_year')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label class="field-group"><span>Semester</span><select name="student_registration_semester" required>@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected(old('student_registration_semester', $registrationSemester) === $value)>{{ $label }}</option>@endforeach</select></label>
            </div>
            <p class="form-help">Students may identify whether they are taking NSTP 1 or continuing with NSTP 2 after completing NSTP 1 in an earlier semester.</p>
            <div class="form-actions"><button class="primary-button compact">Save registration cycle</button></div>
        </form>
    </section>

    <section class="settings-security-grid" id="security">
        <div class="card settings-section-card">
            <div class="settings-section-heading"><span class="settings-section-number">04</span><div><span class="eyebrow">Account protection</span><h3>Automatic logout timeout</h3><p>Users are signed out after this many minutes without a request.</p></div><span class="settings-clock">◷</span></div>
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="settings_section" value="security">
                <label class="field-group"><span>Inactive for</span><div class="timeout-input-wrap"><input type="number" name="inactivity_timeout_minutes" min="1" max="1440" value="{{ old('inactivity_timeout_minutes', $timeoutMinutes) }}" required><strong>minutes</strong></div></label>
                @error('inactivity_timeout_minutes')<small class="field-error">{{ $message }}</small>@enderror
                <div class="timeout-presets" aria-label="Suggested timeout values">@foreach([5, 15, 30, 60, 120, 480] as $minutes)<button type="button" data-timeout-value="{{ $minutes }}">{{ $minutes < 60 ? $minutes.' min' : ($minutes / 60).' hr' }}</button>@endforeach</div>
                <div class="settings-impact-note"><span>!</span><p><strong>Applies to all accounts</strong>Existing sessions use the new timeout on their next request.</p></div>
                <div class="form-actions"><button class="primary-button compact">Save security timeout</button></div>
            </form>
        </div>
        <aside class="card settings-summary-card">
            <span class="eyebrow">Current policy</span><strong>{{ $timeoutMinutes }}</strong><h3>minutes of inactivity</h3><p>An automatic logout is recorded in System Logs whenever this policy expires a session.</p>
            <dl><div><dt>Maximum</dt><dd>24 hours</dd></div><div><dt>Minimum</dt><dd>1 minute</dd></div><div><dt>Last updated by</dt><dd>{{ $setting?->updater?->name ?? 'System default' }}</dd></div><div><dt>Last updated</dt><dd>{{ $setting?->updated_at?->format('M d, Y g:i A') ?? 'Initial setup' }}</dd></div></dl>
            <a class="secondary-outline-button" href="{{ route('admin.system-logs.index', ['action' => 'inactivity_logout']) }}">View automatic logouts</a>
        </aside>
    </section>
</div>
<script src="{{ asset('js/system-settings.js') }}?v={{ filemtime(public_path('js/system-settings.js')) }}"></script>
@endsection
