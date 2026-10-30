@extends('layouts.public-service')
@section('title', 'Privacy Center')

@section('content')
<section class="request-hero"><div class="page-shell request-hero-grid"><div><p class="eyebrow light"><span></span> Privacy center</p><h1>Your information,<br>your rights.</h1><p>Read the current privacy notice, request a copy or correction of your data, withdraw consent, or securely report an incident.</p></div><ol><li><span>01</span><strong>Review</strong><small>Read the exact notice version currently in effect.</small></li><li><span>02</span><strong>Request</strong><small>Submit a traceable privacy-rights request.</small></li><li><span>03</span><strong>Report</strong><small>Alert the institution to a suspected privacy incident.</small></li></ol></div></section>

<section class="request-workspace page-shell">
    <div class="request-form">
        @if(session('status'))<div class="request-alert success" role="status"><strong>{{ session('status') }}</strong></div>@endif
        @if($errors->any())<div class="request-alert error" role="alert"><strong>Please review the submission.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="request-form-section"><div class="request-section-heading"><span>01</span><div><p>Effective {{ $notice->effective_at->format('M d, Y') }}</p><h2>{{ $notice->title }} · Version {{ $notice->version }}</h2></div></div><p style="white-space:pre-line">{{ $notice->content }}</p></section>

        <form method="POST" action="{{ route('privacy.requests.store') }}" class="request-form-section">@csrf
            <div class="request-section-heading"><span>02</span><div><p>Data subject rights</p><h2>Submit a privacy request</h2></div></div>
            <div class="request-field-grid">
                <label><span>Full name *</span><input name="requester_name" value="{{ old('requester_name', $user?->name) }}" required maxlength="150"></label>
                <label><span>Email *</span><input type="email" name="requester_email" value="{{ old('requester_email', $user?->email) }}" required></label>
                <label><span>Request type *</span><select name="request_type" required>@foreach(\App\Models\DataPrivacyRequest::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('request_type')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label class="full"><span>Request details *</span><textarea name="details" rows="5" maxlength="5000" required>{{ old('details') }}</textarea></label>
            </div>
            <label class="privacy-check"><input type="checkbox" name="privacy_consent" value="1" required><span>I acknowledge Privacy Notice version {{ $notice->version }} and consent to processing this request.</span></label>
            <button class="request-submit" type="submit">Submit privacy request →</button>
        </form>

        <form method="POST" action="{{ route('privacy.incidents.store') }}" class="request-form-section">@csrf
            <div class="request-section-heading"><span>03</span><div><p>Security and accountability</p><h2>Report a suspected incident</h2></div></div>
            <div class="request-field-grid">
                <label><span>Incident title *</span><input name="title" value="{{ old('title') }}" required maxlength="180"></label>
                <label><span>Severity *</span><select name="severity" required>@foreach(\App\Models\PrivacyIncident::SEVERITIES as $severity)<option value="{{ $severity }}">{{ str($severity)->headline() }}</option>@endforeach</select></label>
                <label><span>When discovered *</span><input type="datetime-local" name="discovered_at" value="{{ old('discovered_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" required></label>
                <label class="full"><span>What happened? Do not include passwords. *</span><textarea name="description" rows="5" maxlength="5000" required>{{ old('description') }}</textarea></label>
            </div>
            <button class="request-submit" type="submit">Report incident securely →</button>
        </form>
    </div>
</section>
@endsection
