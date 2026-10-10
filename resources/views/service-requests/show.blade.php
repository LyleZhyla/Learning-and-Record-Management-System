@extends('layouts.public-service')

@section('title', 'Request '.$serviceRequest->reference_code)

@section('content')
<section class="request-result-section">
    <div class="page-shell request-result-shell">
        @if (session('status'))<div class="request-alert success" role="status"><strong>Request received.</strong><span>{{ session('status') }}</span></div>@endif
        <div class="request-result-card">
            <div class="request-result-mark" aria-hidden="true">✓</div>
            <p class="eyebrow dark"><span></span> Request status</p>
            <h1>{{ $serviceRequest->statusLabel() }}</h1>
            <p>Your request is now recorded by the Smart NSTP platform. Keep the reference number below for follow-ups with the NSTP Office.</p>
            <div class="reference-box"><span>Reference number</span><strong>{{ $serviceRequest->reference_code }}</strong><button type="button" data-copy-reference="{{ $serviceRequest->reference_code }}">Copy</button></div>
            <dl class="request-summary">
                <div><dt>Service</dt><dd>{{ $serviceRequest->requestTypeLabel() }}@if($serviceRequest->assistance_type)<small>{{ $serviceRequest->assistanceTypeLabel() }}</small>@endif</dd></div>
                <div><dt>Submitted</dt><dd>{{ $serviceRequest->created_at->format('M d, Y · h:i A') }}</dd></div>
                @if($serviceRequest->event_date)<div><dt>Activity date</dt><dd>{{ $serviceRequest->event_date->format('M d, Y') }}</dd></div>@endif
                <div><dt>Current status</dt><dd><span class="request-status {{ $serviceRequest->status }}">{{ $serviceRequest->statusLabel() }}</span></dd></div>
            </dl>
            @if($serviceRequest->status_note)<div class="status-note"><strong>Update from the NSTP Office</strong><p>{{ $serviceRequest->status_note }}</p></div>@endif
            <div class="request-next"><h2>What happens next?</h2><ol><li><span>1</span><p><strong>Initial review</strong>The NSTP Office checks your details and supporting file.</p></li><li><span>2</span><p><strong>Verification or coordination</strong>Eligibility, records, schedule, and available personnel are confirmed.</p></li><li><span>3</span><p><strong>Office update</strong>You will be contacted through the email address or phone number submitted.</p></li></ol></div>
            <div class="request-result-actions"><a href="{{ route('service-requests.create') }}">Submit another request</a><a class="primary" href="{{ route('landing') }}">Return to Smart NSTP</a></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>document.querySelector('[data-copy-reference]')?.addEventListener('click',async function(){try{await navigator.clipboard.writeText(this.dataset.copyReference);this.textContent='Copied';}catch(error){this.textContent='Copy unavailable';}});</script>
@endpush
