@extends('layouts.student')
@section('title', 'Profile & Security')
@section('page-title', 'Profile & Security')

@php
    $religions = config('student_details.religions');
    $relationships = config('student_details.relationships');
    $bloodTypes = config('student_details.blood_types');
    $yearSections = config('student_details.year_sections');
    $savedReligion = old('religion_selection', $details && in_array($details->religion, $religions, true) ? $details->religion : ($details ? 'Others' : ''));
    $savedReligionOther = old('religion_other', $savedReligion === 'Others' ? $details?->religion : '');
    $savedYearSection = old('year_section_selection', $details && in_array($details->year_section, array_slice($yearSections, 0, -1), true) ? $details->year_section : ($details ? 'Others' : ''));
    $savedYearSectionOther = old('year_section_other', $savedYearSection === 'Others' ? $details?->year_section : '');
    $isEditing = request()->boolean('edit') || old('_profile_editor') === '1';
    $displayName = collect([$details?->first_name, $details?->middle_name, $details?->last_name, $details?->extension_name])->filter()->implode(' ') ?: $user->name;
    $currentAddress = collect([$details?->barangay, $details?->city_municipality, $details?->province])->filter()->implode(', ');
    $birthPlace = collect([$details?->birth_city_municipality, $details?->birth_province])->filter()->implode(', ');
    $profileDraftKey = 'smartNstpProfileDraft:'.$user->id;
@endphp

@section('content')
<div class="page-actions student-profile-heading"><div><h2>Your student information</h2><p>{{ $isEditing ? 'Update your details using the guided choices and validation used during registration.' : 'Review your saved personal, emergency contact, and academic details.' }}</p></div>@if($isEditing)<a class="secondary-outline-button" href="{{ route('student.profile.edit') }}" data-profile-draft-cancel>Cancel editing</a>@else<a class="primary-button compact" href="{{ route('student.profile.edit', ['edit' => 1]) }}">Edit profile</a>@endif</div>

@if (! $isEditing)
<section class="card student-profile-overview">
    <div class="student-profile-identity">
        <div class="profile-photo-preview">
            @if ($user->profile_photo_path)
                <img src="{{ route('profile.photo', ['v' => $user->updated_at?->timestamp]) }}" alt="Profile photo of {{ $user->name }}">
            @else
                <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            @endif
        </div>
        <div><span class="eyebrow">Student profile</span><h3>{{ $displayName }}</h3><p>{{ $details?->student_number ?: 'Student number not provided' }} · {{ $user->email }}</p></div>
    </div>

    <div class="student-profile-detail-groups">
        <section><div class="student-profile-group-heading"><span>01</span><div><strong>Personal Information</strong><small>Identity and contact details</small></div></div><dl class="student-profile-details">
            <div><dt>Full name</dt><dd>{{ $displayName }}</dd></div>
            <div><dt>Email address</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Contact number</dt><dd>{{ $details?->contact_number ?: 'Not provided' }}</dd></div>
            <div><dt>Date of birth</dt><dd>{{ $details?->date_of_birth ? \Illuminate\Support\Carbon::parse($details->date_of_birth)->format('F j, Y') : 'Not provided' }}</dd></div>
            <div><dt>Sex</dt><dd>{{ $details?->sex ?: 'Not provided' }}</dd></div>
            <div><dt>Blood type</dt><dd>{{ $details?->blood_type ?: 'Not provided' }}</dd></div>
            <div><dt>Religion</dt><dd>{{ $details?->religion ?: 'Not provided' }}</dd></div>
            <div class="full"><dt>Current address</dt><dd>{{ $currentAddress ?: 'Not provided' }}</dd></div>
            <div class="full"><dt>Place of birth</dt><dd>{{ $birthPlace ?: 'Not provided' }}</dd></div>
        </dl></section>

        <section><div class="student-profile-group-heading"><span>02</span><div><strong>Emergency Contact</strong><small>Person to contact during an emergency</small></div></div><dl class="student-profile-details">
            <div><dt>Full name</dt><dd>{{ $details?->emergency_contact_name ?: 'Not provided' }}</dd></div>
            <div><dt>Relationship</dt><dd>{{ $details?->emergency_relationship ?: 'Not provided' }}</dd></div>
            <div><dt>Contact number</dt><dd>{{ $details?->emergency_contact_number ?: 'Not provided' }}</dd></div>
            <div class="full"><dt>Address</dt><dd>{{ $details?->emergency_same_address ? ($currentAddress ?: 'Same as student address') : ($details?->emergency_address ?: 'Not provided') }}</dd></div>
        </dl></section>

        <section><div class="student-profile-group-heading"><span>03</span><div><strong>Academic Information</strong><small>Current college and program details</small></div></div><dl class="student-profile-details">
            <div><dt>Student number</dt><dd>{{ $details?->student_number ?: 'Not provided' }}</dd></div>
            <div><dt>Year and section</dt><dd>{{ $details?->year_section ?: 'Not provided' }}</dd></div>
            <div class="full"><dt>College</dt><dd>{{ $details?->college ?: 'Not provided' }}</dd></div>
            <div class="full"><dt>Course</dt><dd>{{ $details?->course ?: 'Not provided' }}</dd></div>
            <div class="full"><dt>Major</dt><dd>{{ $details?->major ?: 'Not provided' }}</dd></div>
        </dl></section>
    </div>
</section>
@else
@if ($errors->any())
    <div class="alert danger"><strong>Please check the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('student.profile.details.update') }}" enctype="multipart/form-data" id="student-profile-form" class="student-details-form" data-profile-draft-key="{{ $profileDraftKey }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="_profile_editor" value="1">

    <section class="card student-profile-section">
        <div class="card-heading"><div><span class="eyebrow">Account picture</span><h3>Profile photo</h3><p>You may update your account picture together with your student information.</p></div></div>
        <x-profile-photo-field :user="$user" />
    </section>

    <section class="card student-profile-section">
        <div class="card-heading"><div><span class="eyebrow">Part I</span><h3>Personal Information</h3><p>Use your official name and current home address.</p></div></div>
        <div class="registration-grid four-columns">
            <label class="registration-field"><span>Last Name *</span><input name="last_name" value="{{ old('last_name', $details?->last_name) }}" maxlength="100" required autocomplete="family-name"></label>
            <label class="registration-field"><span>First Name *</span><input name="first_name" value="{{ old('first_name', $details?->first_name ?? ($details ? '' : $user->name)) }}" maxlength="100" required autocomplete="given-name"></label>
            <div class="registration-field"><span>Extension Name *</span><input id="extension-name" name="extension_name" value="{{ old('extension_name', $details?->extension_name) }}" maxlength="30"><label class="inline-check"><input id="extension-na" name="extension_name_na" type="checkbox" value="1" @checked(old('extension_name_na', $details && blank($details->extension_name)))> N/A</label></div>
            <div class="registration-field"><span>Middle Name *</span><input id="middle-name" name="middle_name" value="{{ old('middle_name', $details?->middle_name) }}" maxlength="100"><label class="inline-check"><input id="middle-na" name="middle_name_na" type="checkbox" value="1" @checked(old('middle_name_na', $details && blank($details->middle_name)))> N/A</label></div>
        </div>

        <h4 class="form-subheading">Current Address</h4>
        <div class="registration-grid three-columns">
            <label class="registration-field"><span>Province *</span><select id="province" name="province_code" data-old-code="{{ old('province_code', $details?->province_code) }}" required><option value="">Select province</option>@foreach(config('philippine_locations.provinces') as $code => $name)<option value="{{ $code }}" data-name="{{ $name }}" @selected(old('province_code', $details?->province_code) === $code)>{{ $name }}</option>@endforeach</select><input id="province-name" name="province" type="hidden" value="{{ old('province', $details?->province) }}"></label>
            <label class="registration-field"><span>City / Municipality *</span><select id="city" name="city_municipality_code" data-old-code="{{ old('city_municipality_code', $details?->city_municipality_code) }}" required disabled><option value="">Select province first</option></select><input id="city-name" name="city_municipality" type="hidden" value="{{ old('city_municipality', $details?->city_municipality) }}"></label>
            <label class="registration-field"><span>Barangay *</span><select id="barangay" name="barangay_code" data-old-code="{{ old('barangay_code', $details?->barangay_code) }}" required disabled><option value="">Select city first</option></select><input id="barangay-name" name="barangay" type="hidden" value="{{ old('barangay', $details?->barangay) }}"></label>
        </div>

        <div class="registration-grid two-columns">
            <label class="registration-field"><span>Date of Birth *</span><input name="date_of_birth" type="date" value="{{ old('date_of_birth', $details?->date_of_birth?->format('Y-m-d') ?? $details?->date_of_birth) }}" max="{{ now()->subDay()->format('Y-m-d') }}" required></label>
            <div><h4 class="form-subheading compact">Place of Birth</h4><div class="registration-grid two-columns"><label class="registration-field"><span>Province *</span><select id="birth-province" name="birth_province_code" data-old-code="{{ old('birth_province_code', $details?->birth_province_code) }}" required><option value="">Select province</option>@foreach(config('philippine_locations.provinces') as $code => $name)<option value="{{ $code }}" data-name="{{ $name }}" @selected(old('birth_province_code', $details?->birth_province_code) === $code)>{{ $name }}</option>@endforeach</select><input id="birth-province-name" name="birth_province" type="hidden" value="{{ old('birth_province', $details?->birth_province) }}"></label><label class="registration-field"><span>City / Municipality *</span><select id="birth-city" name="birth_city_municipality_code" data-old-code="{{ old('birth_city_municipality_code', $details?->birth_city_municipality_code) }}" required disabled><option value="">Select province first</option></select><input id="birth-city-name" name="birth_city_municipality" type="hidden" value="{{ old('birth_city_municipality', $details?->birth_city_municipality) }}"></label></div></div>
        </div>

        <div class="registration-grid three-columns">
            <div class="registration-field"><span>Religion *</span><select id="religion" name="religion_selection" required><option value="">Select religion</option>@foreach($religions as $religion)<option value="{{ $religion }}" @selected($savedReligion === $religion)>{{ $religion }}</option>@endforeach</select><input id="religion-other" name="religion_other" value="{{ $savedReligionOther }}" placeholder="Specify religion" maxlength="120" hidden></div>
            <fieldset class="registration-field choice-field"><legend>Sex *</legend><div><label><input name="sex" type="radio" value="Male" @checked(old('sex', $details?->sex) === 'Male') required> Male</label><label><input name="sex" type="radio" value="Female" @checked(old('sex', $details?->sex) === 'Female') required> Female</label></div></fieldset>
            <label class="registration-field"><span>Blood Type *</span><select name="blood_type" required><option value="">Select blood type</option>@foreach($bloodTypes as $type)<option value="{{ $type }}" @selected(old('blood_type', $details?->blood_type) === $type)>{{ $type }}</option>@endforeach</select></label>
        </div>
        <div class="registration-grid two-columns">
            <label class="registration-field"><span>Contact Number *</span><input name="contact_number" value="{{ old('contact_number', $details?->contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" data-digits-only required autocomplete="tel"><small>11 digits and must begin with 09</small></label>
            <label class="registration-field"><span>Email Address *</span><input name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" required autocomplete="email"></label>
        </div>
    </section>

    <section class="card student-profile-section">
        <div class="card-heading"><div><span class="eyebrow">Part II</span><h3>Emergency Contact</h3><p>Provide a person who can be contacted during an emergency.</p></div></div>
        <div class="registration-grid two-columns">
            <label class="registration-field"><span>Full Name *</span><input name="emergency_contact_name" value="{{ old('emergency_contact_name', $details?->emergency_contact_name) }}" maxlength="150" required></label>
            <label class="registration-field"><span>Relationship *</span><select name="emergency_relationship" required><option value="">Select relationship</option>@foreach($relationships as $relationship)<option value="{{ $relationship }}" @selected(old('emergency_relationship', $details?->emergency_relationship) === $relationship)>{{ $relationship }}</option>@endforeach</select></label>
            <label class="registration-field"><span>Contact Number *</span><input name="emergency_contact_number" value="{{ old('emergency_contact_number', $details?->emergency_contact_number) }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" data-digits-only required><small>11 digits and must begin with 09</small></label>
            <div class="registration-field"><span>Address *</span><textarea id="emergency-address" name="emergency_address" rows="4" maxlength="500">{{ old('emergency_address', $details?->emergency_address) }}</textarea><label class="inline-check"><input id="same-address" name="emergency_same_address" type="checkbox" value="1" @checked(old('emergency_same_address', $details?->emergency_same_address))> Same as student's address</label></div>
        </div>
    </section>

    <section class="card student-profile-section">
        <div class="card-heading"><div><span class="eyebrow">Part III</span><h3>Academic Information</h3><p>Courses and majors are automatically filtered by the selected college.</p></div></div>
        <div class="registration-grid two-columns">
            <label class="registration-field"><span>Student Number *</span><input name="student_number" value="{{ old('student_number', $details?->student_number) }}" inputmode="numeric" pattern="20[0-9]{8}" maxlength="10" placeholder="20XXXXXXXX" data-digits-only required><small>10 digits and must begin with 20</small></label>
            <label class="registration-field"><span>College *</span><select id="academic-college" name="college" required><option value="">Select college</option>@foreach(array_keys(config('academics.colleges')) as $college)<option value="{{ $college }}" @selected(old('college', $details?->college) === $college)>{{ $college }}</option>@endforeach</select></label>
            <label class="registration-field"><span>Course *</span><select id="academic-course" name="course" data-old-value="{{ old('course', $details?->course) }}" required disabled><option value="">Select college first</option></select></label>
            <label class="registration-field"><span>Major *</span><select id="academic-major" name="major" data-old-value="{{ old('major', $details?->major) }}" required disabled><option value="">Select course first</option></select><small>N/A is selected automatically for courses without a major.</small></label>
            <div class="registration-field full"><span>Year and Section *</span><select id="year-section" name="year_section_selection" required><option value="">Select year and section</option>@foreach($yearSections as $yearSection)<option value="{{ $yearSection }}" @selected($savedYearSection === $yearSection)>{{ $yearSection }}</option>@endforeach</select><input id="year-section-other" name="year_section_other" value="{{ $savedYearSectionOther }}" maxlength="80" placeholder="Specify year and section" hidden></div>
        </div>
    </section>

    <div class="student-profile-save"><button class="primary-button compact" type="submit">Save student information <span>✓</span></button></div>
</form>
@endif

<section class="card student-password-card" id="password">
    <div class="card-heading"><div><span class="eyebrow">Authentication</span><h3>Change password</h3><p>Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</p></div></div>
    <form method="POST" action="{{ route('student.password.update') }}" class="settings-form" data-password-rules>
        @csrf @method('PUT')
        <label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>@error('current_password')<small class="field-error">{{ $message }}</small>@enderror
        <label for="new_password">New password</label><input id="new_password" name="password" type="password" autocomplete="new-password" minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}" required>@error('password')<small class="field-error">{{ $message }}</small>@enderror
        <label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <x-password-requirements />
        <div class="form-actions"><button class="primary-button compact" type="submit" disabled>Update password</button></div>
    </form>
</section>

@if($isEditing)
    <script>window.studentProfileAcademics = @json(config('academics.colleges')); window.studentProfileLocationEndpoints = {{ Illuminate\Support\Js::from($locationEndpoints) }};</script>
    <script src="{{ asset('js/student-profile.js') }}?v={{ filemtime(public_path('js/student-profile.js')) }}"></script>
@endif
@if(session('profile_saved'))
    <script>sessionStorage.removeItem(@json($profileDraftKey));</script>
@endif
@endsection
