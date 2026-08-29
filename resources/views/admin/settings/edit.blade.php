@extends('layouts.admin')
@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="page-actions">
    <div><span class="eyebrow">Security configuration</span><h2>Session inactivity</h2><p>Control how long an unused account remains signed in across every SNAPIE role.</p></div>
</div>

<div class="settings-security-grid">
    <section class="card inactivity-setting-card">
        <div class="card-heading"><div><h3>Automatic logout timeout</h3><p>Users are signed out when they make no request for the configured number of minutes.</p></div><span class="settings-clock">◷</span></div>

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <label class="field-group">
                <span>Inactive for</span>
                <div class="timeout-input-wrap"><input type="number" name="inactivity_timeout_minutes" min="1" max="1440" value="{{ old('inactivity_timeout_minutes', $timeoutMinutes) }}" required><strong>minutes</strong></div>
            </label>
            @error('inactivity_timeout_minutes')<small class="field-error">{{ $message }}</small>@enderror

            <div class="timeout-presets" aria-label="Suggested timeout values">
                @foreach([5, 15, 30, 60, 120, 480] as $minutes)
                    <button type="button" data-timeout-value="{{ $minutes }}">{{ $minutes < 60 ? $minutes.' min' : ($minutes / 60).' hr' }}</button>
                @endforeach
            </div>

            <div class="settings-impact-note"><span>!</span><p><strong>Applies to all accounts</strong>Students, facilitators, coordinators, NSTP Admins, and Super Admins will use this timeout. Existing sessions are evaluated on their next request.</p></div>
            <div class="form-actions"><button class="primary-button compact">Save inactivity timeout</button></div>
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
