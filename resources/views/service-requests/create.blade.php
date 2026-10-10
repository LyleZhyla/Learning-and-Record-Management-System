@extends('layouts.public-service')

@section('title', 'Request NSTP Service or Assistance')

@section('content')
<section class="request-hero">
    <div class="page-shell request-hero-grid">
        <div><p class="eyebrow light"><span></span> NSTP online services</p><h1>How can the<br>NSTP Office help?</h1><p>Request official student records or coordinate ceremonial and collaborative assistance through one guided form.</p></div>
        <ol aria-label="Request process"><li><span>01</span><strong>Choose a service</strong><small>Select the record or assistance you need.</small></li><li><span>02</span><strong>Send the details</strong><small>Provide complete and accurate information.</small></li><li><span>03</span><strong>Save the reference</strong><small>Use it to revisit your request status.</small></li></ol>
    </div>
</section>

<section class="request-workspace page-shell">
    <form class="request-form" action="{{ route('service-requests.store') }}" method="POST" enctype="multipart/form-data" data-service-request-form>
        @csrf
        @if ($errors->any())
            <div class="request-alert error" role="alert"><strong>Please review your submission.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="request-form-section" aria-labelledby="service-heading">
            <div class="request-section-heading"><span>01</span><div><p>Request category</p><h2 id="service-heading">What do you need?</h2></div></div>
            <div class="request-type-grid">
                @foreach ($requestTypes as $value => $label)
                    <label class="request-type-card">
                        <input type="radio" name="request_type" value="{{ $value }}" @checked(old('request_type') === $value) required>
                        <span class="request-type-icon" aria-hidden="true">{{ match($value) { 'serial_number' => '#', 'certificate_of_completion' => '✓', 'certification' => '▧', default => '◇' } }}</span>
                        <span><strong>{{ $label }}</strong><small>{{ match($value) { 'serial_number' => 'Request assistance regarding an NSTP serial number.', 'certificate_of_completion' => 'Request proof that NSTP requirements were completed.', 'certification' => 'Request an official NSTP certification for a stated purpose.', default => 'Request ceremonial support or coordinate a collaboration.' } }}</small></span>
                        <i aria-hidden="true"></i>
                    </label>
                @endforeach
            </div>

            <div class="assistance-picker" data-assistance-fields hidden>
                <p class="field-label">Select the assistance needed <span>*</span></p>
                <div class="assistance-grid">
                    @foreach ($assistanceTypes as $value => $label)
                        <label><input type="radio" name="assistance_type" value="{{ $value }}" @checked(old('assistance_type') === $value)><span><strong>{{ $label }}</strong><small>{{ match($value) { 'honor_guard' => 'Formal honor and ceremonial detail', 'colors' => 'Flag and colors presentation', 'marshal' => 'Event flow and crowd assistance', default => 'Joint program, project, or activity' } }}</small></span></label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="request-form-section" aria-labelledby="contact-heading">
            <div class="request-section-heading"><span>02</span><div><p>Requester information</p><h2 id="contact-heading">Who should we contact?</h2></div></div>
            <div class="request-field-grid two">
                <label class="request-field"><span>Full name / requesting officer *</span><input name="requester_name" value="{{ old('requester_name') }}" maxlength="180" autocomplete="name" required></label>
                <label class="request-field"><span>Email address *</span><input type="email" name="email" value="{{ old('email') }}" maxlength="180" autocomplete="email" required></label>
                <label class="request-field"><span>Contact number *</span><input name="contact_number" value="{{ old('contact_number') }}" maxlength="40" autocomplete="tel" required placeholder="09XX XXX XXXX"></label>
                <label class="request-field"><span>Student number <small>if applicable</small></span><input name="student_number" value="{{ old('student_number') }}" maxlength="60"></label>
                <label class="request-field"><span>Program / organization</span><input name="program" value="{{ old('program') }}" maxlength="180" placeholder="e.g. BS Agriculture or office name"></label>
                <label class="request-field" data-record-field><span>Year NSTP 02 completed <small data-completion-year-hint>if applicable</small></span><input type="number" name="graduation_year" value="{{ old('graduation_year') }}" min="1945" max="{{ now()->year + 1 }}" inputmode="numeric" data-serial-required></label>
            </div>
        </section>

        <section class="request-form-section" data-event-fields hidden aria-labelledby="event-heading">
            <div class="request-section-heading"><span>03</span><div><p>Activity information</p><h2 id="event-heading">Tell us about the event.</h2></div></div>
            <div class="request-field-grid two">
                <label class="request-field full"><span>Event / activity name *</span><input name="event_name" value="{{ old('event_name') }}" maxlength="180" data-assistance-required></label>
                <label class="request-field"><span>Event date *</span><input type="date" name="event_date" value="{{ old('event_date') }}" min="{{ now()->toDateString() }}" data-assistance-required></label>
                <label class="request-field"><span>Expected participants</span><input type="number" name="expected_participants" value="{{ old('expected_participants') }}" min="1" max="1000000"></label>
                <label class="request-field full"><span>Venue / location *</span><input name="event_location" value="{{ old('event_location') }}" maxlength="255" data-assistance-required></label>
            </div>
        </section>

        <section class="request-form-section" aria-labelledby="details-heading">
            <div class="request-section-heading"><span data-final-step>03</span><div><p>Request details</p><h2 id="details-heading">Help us understand the request.</h2></div></div>
            <label class="request-field"><span>Purpose and important details *</span><textarea name="purpose" rows="6" maxlength="3000" required placeholder="Explain where the document will be used or describe the support and coordination needed.">{{ old('purpose') }}</textarea><small><span data-purpose-count>0</span> / 3,000 characters</small></label>
            <label class="attachment-zone"><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" data-attachment><span class="attachment-icon">⇧</span><span><strong data-attachment-label>Attach a supporting file (optional)</strong><small>PDF, JPG, JPEG, or PNG · Maximum 5 MB</small></span></label>
            <label class="privacy-check"><input type="checkbox" name="privacy_consent" value="1" @checked(old('privacy_consent')) required><span>I confirm that the information is accurate and consent to its use by the TAU NSTP Office for processing this request. *</span></label>
        </section>

        <div class="request-submit-row"><div><strong>Review before sending</strong><span>Your request cannot be edited after submission.</span></div><button class="request-submit" type="submit">Submit request <span aria-hidden="true">→</span></button></div>
    </form>

    <aside class="request-guide">
        <div class="request-guide-card"><p class="eyebrow dark"><span></span> Before you submit</p><h2>Prepare the right information.</h2><ul><li><span>01</span><p><strong>Records requests</strong>Provide your student number, program, graduation year, and intended use.</p></li><li><span>02</span><p><strong>Assistance requests</strong>Include the activity date, venue, expected participants, and required support.</p></li><li><span>03</span><p><strong>Supporting file</strong>Attach an invitation, event brief, valid record, or other relevant document when available.</p></li></ul></div>
        <div class="request-guide-note"><strong>Important</strong><p>Submitting a request does not guarantee approval or immediate release. The NSTP Office will review completeness, eligibility, and availability.</p></div>
    </aside>
</section>
@endsection

@push('scripts')<script src="{{ asset('js/service-requests.js') }}?v={{ filemtime(public_path('js/service-requests.js')) }}" defer></script>@endpush
