@extends($layout)

@section('title', 'Review '.$registration->reference_code)
@section('page-title', 'Registration Review')

@section('content')
    <div class="back-row"><a href="{{ route($routePrefix.'.registrations.index') }}">← Back to registration reviews</a></div>

    @if(session('status'))
        <div class="alert success-alert">{{ session('status') }}</div>
    @endif

    @if(session('temporary_password'))
        <section class="card credential-reveal" aria-label="New student login credentials">
            <div><span class="eyebrow">Student account created</span><h3>Temporary login credentials</h3><p>{{ session('credentials_email_sent') ? 'The credentials were queued for email delivery. Keep this copy until the student confirms access.' : 'The credentials email could not be queued. Give these credentials to the student securely.' }}</p></div>
            <dl><div><dt>Email</dt><dd>{{ session('temporary_password_email') }}</dd></div><div><dt>Temporary password</dt><dd><code data-temporary-password>{{ session('temporary_password') }}</code><button type="button" data-copy-temporary-password>Copy password</button></dd></div></dl>
        </section>
    @endif

    <section class="card registration-applicant-header">
        <div>
            <span class="eyebrow">{{ $registration->reference_code }}</span>
            <h2>{{ $registration->first_name }} {{ $registration->middle_name }} {{ $registration->last_name }} {{ $registration->extension_name }}</h2>
            <p>Submitted {{ $registration->created_at->format('F d, Y \a\t g:i A') }}</p>
        </div>
        <span class="registration-status status-{{ $registration->status }}">{{ $registration->statusLabel() }}</span>
    </section>

    <div class="registration-review-layout">
        <main class="registration-review-main">
            <section class="card registration-section-card">
                <div class="registration-section-heading">
                    <div><span class="eyebrow">Required files</span><h3>Document checklist</h3></div>
                    <p>Preview the actual files, then record a decision for each one.</p>
                </div>

                <div class="registration-document-grid">
                    @foreach($checklist as $key => $document)
                        <article class="registration-document-card">
                            <div class="registration-document-heading">
                                <div>
                                    <span class="document-icon">{{ $key === 'cor' ? '▤' : '▧' }}</span>
                                    <span><strong>{{ $document['label'] }}</strong><small>{{ $document['name'] ?: 'No stored file' }}</small></span>
                                </div>
                                <span class="document-health {{ $document['complete'] ? 'complete' : 'incomplete' }}">{{ $document['complete'] ? 'File ready' : 'File issue' }}</span>
                            </div>

                            <div class="registration-document-preview">
                                @if($document['exists'] && $document['is_image'])
                                    <img src="{{ route($routePrefix.'.registrations.documents.show', [$registration, $key]) }}" alt="Preview of {{ $document['label'] }}">
                                @elseif($document['exists'] && $document['extension'] === 'pdf')
                                    <iframe src="{{ route($routePrefix.'.registrations.documents.show', [$registration, $key]) }}" title="Preview of {{ $document['label'] }}"></iframe>
                                @else
                                    <div class="missing-document"><strong>Preview unavailable</strong><span>The stored file is missing or unsupported.</span></div>
                                @endif
                            </div>

                            <div class="document-validation-list">
                                <span class="{{ $document['exists'] ? 'valid' : 'invalid' }}">{{ $document['exists'] ? '✓' : '!' }} File exists</span>
                                <span class="{{ $document['valid_type'] ? 'valid' : 'invalid' }}">{{ $document['valid_type'] ? '✓' : '!' }} {{ strtoupper($document['extension'] ?: 'Unknown') }} type</span>
                                <span class="{{ $document['valid_size'] ? 'valid' : 'invalid' }}">{{ $document['valid_size'] ? '✓' : '!' }} {{ $document['size_label'] }} / {{ $document['maximum_size_label'] }} max</span>
                            </div>

                            @if($document['exists'])
                                <div class="document-actions">
                                    <a class="secondary-outline-button" href="{{ route($routePrefix.'.registrations.documents.show', [$registration, $key]) }}" target="_blank" rel="noopener">Open full preview</a>
                                    <a class="secondary-outline-button" href="{{ route($routePrefix.'.registrations.documents.download', [$registration, $key]) }}">Download</a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="card registration-section-card">
                <div class="registration-section-heading"><div><span class="eyebrow">Submitted data</span><h3>Applicant and academic details</h3></div></div>
                <dl class="registration-details-grid">
                    <div><dt>Student number</dt><dd>{{ $registration->student_number }}</dd></div>
                    <div><dt>Email address</dt><dd>{{ $registration->email }}</dd></div>
                    <div><dt>Registration term</dt><dd>{{ ucfirst($registration->semester ?? 'Unspecified') }} · {{ $registration->academic_year ?? 'Legacy record' }}</dd></div>
                    <div><dt>NSTP level</dt><dd>{{ str($registration->nstp_level)->replace('_', ' ')->upper() }}</dd></div>
                    <div><dt>College</dt><dd>{{ $registration->college }}</dd></div>
                    <div><dt>Course / major</dt><dd>{{ $registration->course }}{{ $registration->major ? ' — '.$registration->major : '' }}</dd></div>
                    <div><dt>Year and section</dt><dd>{{ $registration->year_section }}</dd></div>
                    <div><dt>Contact number</dt><dd>{{ $registration->contact_number }}</dd></div>
                    <div><dt>Date of birth</dt><dd>{{ $registration->date_of_birth->format('F d, Y') }}</dd></div>
                    <div><dt>Sex / blood type</dt><dd>{{ $registration->sex }} · {{ $registration->blood_type }}</dd></div>
                    <div class="full"><dt>Home address</dt><dd>{{ $registration->barangay }}, {{ $registration->city_municipality }}, {{ $registration->province }}</dd></div>
                    <div class="full"><dt>Emergency contact</dt><dd>{{ $registration->emergency_contact_name }} ({{ $registration->emergency_relationship }}) · {{ $registration->emergency_contact_number }}</dd></div>
                </dl>
            </section>
        </main>

        <aside class="card registration-decision-card">
            <span class="eyebrow">Admin decision</span>
            <h3>Record document review</h3>
            <p>When both documents are marked “Verified,” the enrollment is approved and an active student account is created automatically. The original registration remains in this list for audit and review.</p>

            <form method="POST" action="{{ route($routePrefix.'.registrations.review', $registration) }}">
                @csrf
                @method('PATCH')
                @foreach(['cor' => 'COR decision', 'formal_photo' => 'Formal photo decision'] as $key => $label)
                    <label class="field-group">
                        <span>{{ $label }}</span>
                        <select name="{{ $key }}_review_status" required>
                            @foreach($documentStatuses as $value => $statusLabel)
                                <option value="{{ $value }}" @selected(old($key.'_review_status', $registration->{$key.'_review_status'}) === $value)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        @error($key.'_review_status')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                @endforeach

                <label class="field-group">
                    <span>Reviewer notes</span>
                    <textarea name="review_notes" rows="6" placeholder="Explain missing, unreadable, or mismatched details. Required when correction is needed.">{{ old('review_notes', $registration->review_notes) }}</textarea>
                    @error('review_notes')<small class="field-error">{{ $message }}</small>@enderror
                </label>

                <button class="primary-button" type="submit">Save review decision</button>
            </form>

            @if($registration->reviewed_at)
                <div class="registration-review-audit">
                    <strong>Last reviewed</strong>
                    <span>{{ $registration->reviewed_at->format('M d, Y · g:i A') }}</span>
                    <small>by {{ $registration->reviewer?->name ?? 'Former administrator' }}</small>
                </div>
            @endif
        </aside>
    </div>
    @if(session('temporary_password'))<script src="{{ asset('js/staff-account-form.js') }}?v={{ filemtime(public_path('js/staff-account-form.js')) }}"></script>@endif
@endsection
