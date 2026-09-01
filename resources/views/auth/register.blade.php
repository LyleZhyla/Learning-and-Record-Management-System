<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Student Registration · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <x-theme-init />
</head>
<body class="registration-body">
    <header class="registration-header">
        <a class="brand" href="{{ route('login') }}">
            <img class="brand-landscape theme-logo-light" src="{{ asset('images/snapie-landscape-light.png') }}" alt="SNAPIE">
            <img class="brand-landscape theme-logo-dark" src="{{ asset('images/snapie-landscape-dark.png') }}" alt="SNAPIE">
        </a>
        <div class="registration-header-actions">
            <span>Already registered?</span>
            <a href="{{ route('login') }}">Sign in</a>
            <x-theme-toggle />
        </div>
    </header>

    <main class="registration-shell">
        <aside class="registration-intro">
            <span class="eyebrow">Student application</span>
            <h1>Begin your NSTP journey.</h1>
            <p>Complete each part carefully. Your Certificate of Registration is required before the personal information section becomes available.</p>
            <div class="registration-requirements">
                <div><span>✓</span><p><strong>Prepare your COR</strong><small>PDF, JPG or PNG · up to 5 MB</small></p></div>
                <div><span>✓</span><p><strong>Use accurate details</strong><small>Information will be reviewed by the NSTP office</small></p></div>
                <div><span>✓</span><p><strong>Formal white-background photo</strong><small>JPG or PNG · up to 3 MB</small></p></div>
            </div>
            <p class="registration-privacy">Your uploaded files are stored privately and are accessible only to authorized personnel.</p>
        </aside>

        <section class="registration-workspace">
            @if (session('status'))
                <div class="registration-success" role="status">
                    <span>✓</span>
                    <div>
                        <strong>{{ session('status') }}</strong>
                        <p>Reference number: <b>{{ session('reference_code') }}</b>. Save this number for follow-up.</p>
                    </div>
                </div>
            @else
                <div class="registration-heading">
                    <div><span class="eyebrow">New student registration</span><h2>Student Information Form</h2></div>
                    <span class="registration-step-label">Step <b id="current-step-number">1</b> of 5</span>
                </div>

                <nav class="registration-progress" aria-label="Registration progress">
                    @foreach (['COR Upload', 'Personal', 'Emergency', 'Academic', 'Photo'] as $index => $label)
                        <button type="button" class="registration-progress-step {{ $index === 0 ? 'active' : '' }}" data-progress-step="{{ $index }}" disabled>
                            <span>{{ $index + 1 }}</span><small>{{ $label }}</small>
                        </button>
                    @endforeach
                </nav>

                @if ($errors->any())
                    <div class="alert danger registration-errors" role="alert">
                        <strong>Please review the highlighted information.</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" id="registration-form" data-server-error-step="{{ $errors->has('email') ? 1 : ($errors->has('student_number') ? 3 : '') }}" novalidate>
                    @csrf

                    <section class="registration-panel active" data-step="0">
                        <div class="section-title"><span>01</span><div><h3>Certificate of Registration</h3><p>Upload your current Certificate of Registration to unlock the form.</p></div></div>
                        <label class="upload-zone" for="cor" id="cor-zone">
                            <input id="cor" name="cor" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                            <span class="upload-icon">↥</span>
                            <strong id="cor-file-name">Choose your Certificate of Registration</strong>
                            <small>Drag and drop or click to browse · PDF, JPG or PNG · maximum 5 MB</small>
                        </label>
                        <div class="registration-note"><span>i</span><p>The next part remains locked until a valid file is selected.</p></div>
                    </section>

                    <section class="registration-panel" data-step="1" hidden>
                        <div class="section-title"><span>02</span><div><h3>Part I — Personal Information</h3><p>Enter your name exactly as it appears in your school records.</p></div></div>
                        <div class="registration-grid four-columns">
                            <label class="registration-field"><span>Last Name *</span><input name="last_name" value="{{ old('last_name') }}" maxlength="100" required autocomplete="family-name"></label>
                            <label class="registration-field"><span>First Name *</span><input name="first_name" value="{{ old('first_name') }}" maxlength="100" required autocomplete="given-name"></label>
                            <div class="registration-field"><span>Extension Name *</span><input id="extension-name" name="extension_name" value="{{ old('extension_name') }}" maxlength="30"><label class="inline-check"><input id="extension-na" name="extension_name_na" type="checkbox" value="1" @checked(old('extension_name_na'))> N/A</label></div>
                            <div class="registration-field"><span>Middle Name *</span><input id="middle-name" name="middle_name" value="{{ old('middle_name') }}" maxlength="100"><label class="inline-check"><input id="middle-na" name="middle_name_na" type="checkbox" value="1" @checked(old('middle_name_na'))> N/A</label></div>
                        </div>

                        <h4 class="form-subheading">Current address</h4>
                        <div class="registration-grid three-columns" data-address-group="current">
                            <label class="registration-field"><span>Province *</span><select id="province" name="province_code" data-old-code="{{ old('province_code') }}" data-old-name="{{ old('province') }}" required><option value="">Select province</option>@foreach (config('philippine_locations.provinces') as $code => $name)<option value="{{ $code }}" data-name="{{ $name }}" @selected(old('province_code') === $code)>{{ $name }}</option>@endforeach</select><input id="province-name" name="province" type="hidden" value="{{ old('province') }}"></label>
                            <label class="registration-field"><span>City / Municipality *</span><select id="city" name="city_municipality_code" data-old-code="{{ old('city_municipality_code') }}" data-old-name="{{ old('city_municipality') }}" required disabled><option value="">Select province first</option></select><input id="city-name" name="city_municipality" type="hidden" value="{{ old('city_municipality') }}"></label>
                            <label class="registration-field"><span>Barangay *</span><select id="barangay" name="barangay_code" data-old-code="{{ old('barangay_code') }}" data-old-name="{{ old('barangay') }}" required disabled><option value="">Select city first</option></select><input id="barangay-name" name="barangay" type="hidden" value="{{ old('barangay') }}"></label>
                        </div>

                        <div class="registration-grid two-columns">
                            <label class="registration-field"><span>Date of Birth *</span><input name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ now()->subDay()->format('Y-m-d') }}" required></label>
                            <div><h4 class="form-subheading compact">Place of Birth</h4><div class="registration-grid two-columns" data-address-group="birth"><label class="registration-field"><span>Province *</span><select id="birth-province" name="birth_province_code" data-old-code="{{ old('birth_province_code') }}" data-old-name="{{ old('birth_province') }}" required><option value="">Select province</option>@foreach (config('philippine_locations.provinces') as $code => $name)<option value="{{ $code }}" data-name="{{ $name }}" @selected(old('birth_province_code') === $code)>{{ $name }}</option>@endforeach</select><input id="birth-province-name" name="birth_province" type="hidden" value="{{ old('birth_province') }}"></label><label class="registration-field"><span>City / Municipality *</span><select id="birth-city" name="birth_city_municipality_code" data-old-code="{{ old('birth_city_municipality_code') }}" data-old-name="{{ old('birth_city_municipality') }}" required disabled><option value="">Select province first</option></select><input id="birth-city-name" name="birth_city_municipality" type="hidden" value="{{ old('birth_city_municipality') }}"></label></div></div>
                        </div>

                        <div class="registration-grid three-columns">
                            <div class="registration-field"><span>Religion *</span><select id="religion" name="religion_selection" required><option value="">Select religion</option>@foreach (['Roman Catholic','Islam','Iglesia ni Cristo','Philippine Independent Church (Aglipayan)','Seventh-day Adventist','Bible Baptist','United Church of Christ in the Philippines','Jehovah’s Witnesses','Church of Christ','Jesus Is Lord Church','Members Church of God International','Church of Jesus Christ of Latter-day Saints','Evangelical / Born Again Christian','Other Protestant','Orthodox Christian','Hinduism','Buddhism','Judaism','Baháʼí Faith','Sikhism','Indigenous Philippine Folk Religion','No Religion / Atheist / Agnostic','Prefer not to say','Others'] as $religion)<option value="{{ $religion }}" @selected(old('religion_selection') === $religion)>{{ $religion }}</option>@endforeach</select><input id="religion-other" name="religion_other" value="{{ old('religion_other') }}" placeholder="Specify religion" maxlength="120" hidden></div>
                            <fieldset class="registration-field choice-field"><legend>Sex *</legend><div><label><input name="sex" type="radio" value="Male" @checked(old('sex') === 'Male') required> Male</label><label><input name="sex" type="radio" value="Female" @checked(old('sex') === 'Female') required> Female</label></div></fieldset>
                            <label class="registration-field"><span>Blood Type *</span><select name="blood_type" required><option value="">Select blood type</option>@foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $type)<option value="{{ $type }}" @selected(old('blood_type') === $type)>{{ $type }}</option>@endforeach</select></label>
                        </div>
                        <div class="registration-grid two-columns">
                            <label class="registration-field"><span>Contact Number *</span><input name="contact_number" value="{{ old('contact_number') }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" required autocomplete="tel"><small>11 digits and must begin with 09</small></label>
                            <label class="registration-field"><span>Email Address *</span><input name="email" type="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email">@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
                        </div>
                    </section>

                    <section class="registration-panel" data-step="2" hidden>
                        <div class="section-title"><span>03</span><div><h3>Part II — Emergency Contact</h3><p>Provide someone we can contact in case of an emergency.</p></div></div>
                        <div class="registration-grid two-columns">
                            <label class="registration-field"><span>Full Name *</span><input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" maxlength="150" required></label>
                            <label class="registration-field"><span>Relationship *</span><select name="emergency_relationship" required><option value="">Select relationship</option>@foreach (['Mother','Father','Sibling','Aunt','Uncle','Cousin','Nephew','Niece','Grandmother','Grandfather','Guardian'] as $relationship)<option value="{{ $relationship }}" @selected(old('emergency_relationship') === $relationship)>{{ $relationship }}</option>@endforeach</select></label>
                            <label class="registration-field"><span>Contact Number *</span><input name="emergency_contact_number" value="{{ old('emergency_contact_number') }}" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" required><small>11 digits and must begin with 09</small></label>
                            <div class="registration-field"><span>Address *</span><textarea id="emergency-address" name="emergency_address" rows="4" maxlength="500">{{ old('emergency_address') }}</textarea><label class="inline-check"><input id="same-address" name="emergency_same_address" type="checkbox" value="1" @checked(old('emergency_same_address'))> Same as student's address</label></div>
                        </div>
                    </section>

                    <section class="registration-panel" data-step="3" hidden>
                        <div class="section-title"><span>04</span><div><h3>Part III — Academic Information</h3><p>Use the information shown in your current school records.</p></div></div>
                        <div class="registration-grid two-columns">
                            <label class="registration-field"><span>Student Number *</span><input name="student_number" value="{{ old('student_number') }}" inputmode="numeric" pattern="20[0-9]{8}" maxlength="10" placeholder="20XXXXXXXX" required><small>10 digits and must begin with 20</small>@error('student_number')<small class="field-error">{{ $message }}</small>@enderror</label>
                            <label class="registration-field"><span>College *</span><select id="academic-college" name="college" data-old-value="{{ old('college') }}" required><option value="">Select college</option>@foreach (array_keys(config('academics.colleges')) as $college)<option value="{{ $college }}" @selected(old('college') === $college)>{{ $college }}</option>@endforeach</select></label>
                            <label class="registration-field"><span>Course *</span><select id="academic-course" name="course" data-old-value="{{ old('course') }}" required disabled><option value="">Select college first</option></select></label>
                            <label class="registration-field"><span>Major *</span><select id="academic-major" name="major" data-old-value="{{ old('major') }}" required disabled><option value="">Select course first</option></select><small>N/A is automatically shown for courses without a major.</small></label>
                            <div class="registration-field full"><span>Year and Section *</span><select id="year-section" name="year_section_selection" required><option value="">Select year and section</option>@foreach (['1A','1B','1C','1D','1E','1F','Others'] as $yearSection)<option value="{{ $yearSection }}" @selected(old('year_section_selection') === $yearSection)>{{ $yearSection }}</option>@endforeach</select><input id="year-section-other" name="year_section_other" value="{{ old('year_section_other') }}" maxlength="80" placeholder="Specify year and section" hidden></div>
                        </div>
                    </section>

                    <section class="registration-panel" data-step="4" hidden>
                        <div class="section-title"><span>05</span><div><h3>Part IV — Formal Picture</h3><p>Upload a clear, recent formal picture taken against a plain white background.</p></div></div>
                        <div class="photo-upload-layout">
                            <label class="formal-photo-preview" for="formal-photo"><input id="formal-photo" name="formal_photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required><img id="photo-preview" alt="Formal photo preview" hidden><span id="photo-placeholder">👤</span></label>
                            <div class="photo-guidelines"><h4>Photo guidelines</h4><ul><li>Plain white background</li><li>Formal or school-appropriate attire</li><li>Face the camera directly</li><li>No filters, hats, or sunglasses</li><li>JPG or PNG, maximum 3 MB</li></ul><label class="consent-check"><input name="privacy_consent" type="checkbox" value="1" required> I certify that all information and uploaded documents are complete, true, and correct.</label></div>
                        </div>
                    </section>

                    <div class="registration-actions">
                        <button class="registration-back" id="registration-back" type="button" hidden>← Back</button>
                        <button class="primary-button compact" id="registration-next" type="button" disabled>Continue to Part I <span>→</span></button>
                        <button class="primary-button compact" id="registration-submit" type="submit" hidden>Submit registration <span>✓</span></button>
                    </div>
                </form>
            @endif
        </section>
    </main>
    <script src="{{ asset('js/theme.js') }}"></script>
    @if(session('status'))
        <script>try { sessionStorage.removeItem('smartNstpRegistrationDraft'); } catch (error) {}</script>
    @endif
    @unless(session('status'))
        <script>window.registrationAcademics = @json(config('academics.colleges'));</script>
        <script>window.registrationLocationEndpoints = {{ Illuminate\Support\Js::from($locationEndpoints) }};</script>
        <script src="{{ asset('js/student-registration.js') }}?v={{ filemtime(public_path('js/student-registration.js')) }}"></script>
    @endunless
</body>
</html>
