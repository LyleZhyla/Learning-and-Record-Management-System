@extends('layouts.nstp-admin')

@section('title', 'Review '.$serviceRequest->reference_code)
@section('page-title', 'Review Service Request')

@section('content')
<a class="service-admin-back" href="{{ route('nstp_admin.service-requests.index') }}">← Back to service requests</a>
<section class="service-request-detail-hero">
    <div><span class="eyebrow">{{ $serviceRequest->requestTypeLabel() }}</span><h2>{{ $serviceRequest->reference_code }}</h2><p>Submitted {{ $serviceRequest->created_at->format('F d, Y · g:i A') }} by {{ $serviceRequest->requesterDisplayName() }}.</p></div>
    <span class="service-request-status status-{{ $serviceRequest->status }}"><i></i>{{ $serviceRequest->statusLabel() }}</span>
</section>

<section class="service-request-detail-grid">
    <article class="card service-request-detail-card">
        <div class="section-heading"><div><span class="eyebrow">Application details</span><h3>Requester and request information</h3></div></div>
        <dl class="service-request-detail-list">
            <div><dt>Requester</dt><dd>{{ $serviceRequest->requesterDisplayName() }}</dd></div>
            <div><dt>Email address</dt><dd><a href="mailto:{{ $serviceRequest->email }}">{{ $serviceRequest->email }}</a></dd></div>
            <div><dt>Contact number</dt><dd>{{ $serviceRequest->contact_number }}</dd></div>
            <div><dt>Student number</dt><dd>{{ $serviceRequest->student_number ?: 'Not provided' }}</dd></div>
            <div><dt>Program</dt><dd>{{ $serviceRequest->program ?: 'Not provided' }}</dd></div>
            @if($serviceRequest->graduation_year)<div><dt>NSTP 02 completion year</dt><dd>{{ $serviceRequest->graduation_year }}</dd></div>@endif
            @if($serviceRequest->assistanceTypeLabel())<div><dt>Assistance type</dt><dd>{{ $serviceRequest->assistanceTypeLabel() }}</dd></div>@endif
            @if($serviceRequest->event_name)<div><dt>Event</dt><dd>{{ $serviceRequest->event_name }} · {{ $serviceRequest->event_date?->format('M d, Y') }} · {{ $serviceRequest->event_location }}</dd></div>@endif
        </dl>
        <div class="service-request-purpose"><span>Purpose / request details</span><p>{{ $serviceRequest->purpose }}</p></div>
        @if($serviceRequest->attachment_path)<a class="secondary-outline-button service-attachment-button" href="{{ route('nstp_admin.service-requests.attachment', $serviceRequest) }}">↓ Download {{ $serviceRequest->attachment_original_name ?: 'supporting document' }}</a>@endif
    </article>

    <aside class="card service-status-panel">
        <div class="section-heading"><div><span class="eyebrow">Applicant-facing update</span><h3>Update application status</h3><p>The status and note will appear on the public reference page.</p></div></div>
        <form method="POST" action="{{ route('nstp_admin.service-requests.update', $serviceRequest) }}" class="stack-form">
            @csrf @method('PATCH')
            <label class="field-group"><span>Application status</span><select name="status" required>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $serviceRequest->status) === $value)>{{ $label }}</option>@endforeach</select>@error('status')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>NSTP Office note</span><textarea name="status_note" rows="6" maxlength="3000" placeholder="Explain the update, missing information, next step, or release instructions.">{{ old('status_note', $serviceRequest->status_note) }}</textarea><small>Required when requesting information or declining an application.</small>@error('status_note')<small class="field-error">{{ $message }}</small>@enderror</label>
            @if($serviceRequest->processed_at)<div class="service-last-update"><small>Last processed</small><strong>{{ $serviceRequest->processed_at->format('M d, Y · g:i A') }}</strong><span>by {{ $serviceRequest->processor?->name ?? 'Former NSTP Admin' }}</span></div>@endif
            <button class="primary-button" type="submit">Save and publish status</button>
        </form>
        <div class="service-status-guide"><strong>Suggested flow</strong><ol><li>Submitted</li><li>Under review</li><li>Approved or needs information</li><li>Completed</li></ol></div>
    </aside>
</section>
@endsection
