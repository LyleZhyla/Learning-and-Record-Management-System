@extends('layouts.admin')
@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="page-actions">
    <div><span class="eyebrow">System configuration</span><h2>Branding, security, and registration</h2><p>Adapt the platform to your institution, then control session security and the active registration period.</p></div>
</div>

<div class="settings-security-grid">
    <section class="card inactivity-setting-card">
        <div class="card-heading"><div><h3>Automatic logout timeout</h3><p>Users are signed out when they make no request for the configured number of minutes.</p></div><span class="settings-clock">◷</span></div>

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="registration-cycle-settings">
                <div class="card-heading"><div><h3>Institutional branding</h3><p>These names and logos are reused across the portals. Uploaded logos should be square PNG, JPG, or WebP files up to 2 MB.</p></div></div>
                <div class="form-grid">
                    <label class="field-group full"><span>University / organization name</span><input name="organization_name" value="{{ old('organization_name', $branding['organization_name']) }}" maxlength="150" required></label>
                    <label class="field-group"><span>Organization short name</span><input name="organization_short_name" value="{{ old('organization_short_name', $branding['organization_short_name']) }}" maxlength="20" required></label>
                    <label class="field-group"><span>Program short name</span><input name="program_short_name" value="{{ old('program_short_name', $branding['program_short_name']) }}" maxlength="20" required></label>
                    <label class="field-group full"><span>Program name</span><input name="program_name" value="{{ old('program_name', $branding['program_name']) }}" maxlength="150" required></label>
                    <label class="field-group full"><span>Platform name</span><input name="platform_name" value="{{ old('platform_name', $branding['platform_name']) }}" maxlength="180" required></label>
                    <label class="field-group full"><span>Official website</span><input type="url" name="organization_website" value="{{ old('organization_website', $branding['organization_website']) }}" maxlength="255" placeholder="https://example.edu.ph"></label>
                    <label class="field-group"><span>Organization logo</span><input class="file-input" type="file" name="organization_logo" accept=".png,.jpg,.jpeg,.webp"><small>Current: {{ $branding['organization_logo_path'] ? basename($branding['organization_logo_path']) : 'Built-in TAU logo' }}</small></label>
                    <label class="field-group"><span>Program logo</span><input class="file-input" type="file" name="program_logo" accept=".png,.jpg,.jpeg,.webp"><small>Current: {{ $branding['program_logo_path'] ? basename($branding['program_logo_path']) : 'Built-in NSTP logo' }}</small></label>
                    @if($branding['organization_logo_path'])<label class="review-category-check"><input type="checkbox" name="remove_organization_logo" value="1"> Restore built-in organization logo</label>@endif
                    @if($branding['program_logo_path'])<label class="review-category-check"><input type="checkbox" name="remove_program_logo" value="1"> Restore built-in program logo</label>@endif
                </div>
            </div>
            <label class="field-group">
                <span>Inactive for</span>
                <div class="timeout-input-wrap"><input type="number" name="inactivity_timeout_minutes" min="1" max="1440" value="{{ old('inactivity_timeout_minutes', $timeoutMinutes) }}" required><strong>minutes</strong></div>
            </label>
            @error('inactivity_timeout_minutes')<small class="field-error">{{ $message }}</small>@enderror

            <div class="registration-cycle-settings">
                <div class="card-heading"><div><h3>Student registration cycle</h3><p>Only the configured academic year and semester will be attached to new public registrations.</p></div></div>
                <label class="field-group"><span>Registration status</span><select name="student_registration_open" required><option value="1" @selected(old('student_registration_open', $registrationOpen ? '1' : '0') === '1')>Open — accept registrations</option><option value="0" @selected(old('student_registration_open', $registrationOpen ? '1' : '0') === '0')>Closed — stop registrations</option></select></label>
                <div class="form-grid">
                    <label class="field-group"><span>Academic year</span><input name="student_registration_academic_year" value="{{ old('student_registration_academic_year', $registrationAcademicYear) }}" pattern="\d{4}-\d{4}" placeholder="2026-2027" required>@error('student_registration_academic_year')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="field-group"><span>Semester</span><select name="student_registration_semester" required>@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected(old('student_registration_semester', $registrationSemester) === $value)>{{ $label }}</option>@endforeach</select></label>
                </div>
                <p class="form-help">Students may identify whether they are taking NSTP 1 or are continuing with NSTP 2 after completing NSTP 1 in an earlier semester.</p>
            </div>

            <div class="timeout-presets" aria-label="Suggested timeout values">
                @foreach([5, 15, 30, 60, 120, 480] as $minutes)
                    <button type="button" data-timeout-value="{{ $minutes }}">{{ $minutes < 60 ? $minutes.' min' : ($minutes / 60).' hr' }}</button>
                @endforeach
            </div>

            <div class="settings-impact-note"><span>!</span><p><strong>Applies to all accounts</strong>Students, facilitators, coordinators, NSTP Admins, and Super Admins will use this timeout. Existing sessions are evaluated on their next request.</p></div>
            <div class="form-actions"><button class="primary-button compact">Save system settings</button></div>
        </form>
    </section>

    <aside class="card settings-summary-card">
        <span class="eyebrow">Current policy</span>
        <strong>{{ $timeoutMinutes }}</strong>
        <h3>minutes of inactivity</h3>
        <p>An automatic logout is recorded in System Logs whenever this policy expires a session.</p>
        <dl>
            <div><dt>Maximum configurable value</dt><dd>24 hours</dd></div>
            <div><dt>Minimum configurable value</dt><dd>1 minute</dd></div>
            <div><dt>Last updated by</dt><dd>{{ $setting?->updater?->name ?? 'System default' }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ $setting?->updated_at?->format('M d, Y g:i A') ?? 'Initial setup' }}</dd></div>
        </dl>
        <a class="secondary-outline-button" href="{{ route('admin.system-logs.index', ['action' => 'inactivity_logout']) }}">View automatic logouts</a>
    </aside>
</div>
<script src="{{ asset('js/system-settings.js') }}"></script>
@endsection
