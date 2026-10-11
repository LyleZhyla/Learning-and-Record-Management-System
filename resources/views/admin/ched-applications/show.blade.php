@extends($layout)

@section('title', $application->reference_number)
@section('page-title', 'CHED Application Tracker')

@section('content')
    @php
        $milestones = ['emailed' => 'Email sent', 'acknowledged' => 'Acknowledged', 'under_review' => 'Under review', 'approved' => 'Approved', 'serials_released' => 'Serials released'];
        $statusOrder = ['draft' => -1, 'workbook_prepared' => -1, 'emailed' => 0, 'acknowledged' => 1, 'under_review' => 2, 'returned' => 2, 'approved' => 3, 'serials_released' => 4, 'closed' => 4];
        $currentStep = $statusOrder[$application->status] ?? -1;
    @endphp

    <section class="ched-detail-hero">
        <div>
            <a class="ched-back-link" href="{{ route($routePrefix.'.ched-applications.index') }}">← All CHED records</a>
            <span class="eyebrow">{{ $application->reference_number }}</span>
            <h2>{{ $application->statusLabel() }}</h2>
            <p>{{ $application->academic_year }} · {{ \App\Models\NstpSection::SEMESTERS[$application->semester] ?? str($application->semester)->headline() }} · {{ number_format($application->student_count) }} eligible CWTS/LTS student{{ $application->student_count === 1 ? '' : 's' }} at the time of recording.</p>
        </div>
        <div class="ched-detail-actions">
            <a class="secondary-button" href="{{ route($routePrefix.'.reports.index', ['type' => 'ched_semestral', 'academic_year' => $application->academic_year, 'semester' => $application->semester]) }}">Open source report</a>
        </div>
    </section>

    @if($errors->any())<div class="alert danger"><strong>The status update could not be saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(in_array($application->status, ['draft', 'workbook_prepared'], true))
        <div class="ched-legacy-notice"><strong>Legacy tracking record</strong><span>This record was created before email submission was required. Open the CHED report, send it manually, then mark this record as “Emailed to CHED.”</span></div>
    @elseif($application->status === 'returned')
        <div class="ched-returned-notice"><strong>CHED returned this submission for correction.</strong><span>Correct and download the workbook from Reports Center, resend it by email, then record the new emailed date below.</span></div>
    @endif

    <section class="card ched-progress-card" aria-label="CHED application progress">
        <div class="section-heading"><div><span class="eyebrow">Submission progress</span><h3>Manual follow-up workflow</h3><p>Statuses reflect actions recorded by administrators; SNAPIE does not communicate with CHED automatically.</p></div></div>
        <div class="ched-progress-track">
            @foreach($milestones as $status => $label)
                @php($stepIndex = $statusOrder[$status])
                <div class="ched-progress-step {{ $currentStep > $stepIndex ? 'complete' : ($currentStep === $stepIndex ? 'current' : '') }}">
                    <span>{{ $currentStep > $stepIndex ? '✓' : $loop->iteration }}</span><strong>{{ $label }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    <div class="ched-detail-grid">
        <section class="card ched-submission-summary">
            <div class="section-heading"><div><span class="eyebrow">Email submission</span><h3>Recorded details</h3><p>The source workbook remains in Reports Center.</p></div><span class="ched-status-badge status-{{ $application->status }}"><i></i>{{ $application->statusLabel() }}</span></div>
            <dl class="ched-detail-list">
                <div><dt>Internal reference</dt><dd>{{ $application->reference_number }}</dd></div>
                <div><dt>Recipient email</dt><dd>{{ $application->submission_email ?: 'Not recorded' }}</dd></div>
                <div><dt>Date emailed</dt><dd>{{ $application->submitted_at?->format('F d, Y') ?? 'Not recorded' }}</dd></div>
                <div><dt>CHED/email reference</dt><dd>{{ $application->ched_reference ?: 'Not recorded' }}</dd></div>
                <div><dt>CHED acknowledgment</dt><dd>{{ $application->acknowledged_at?->format('F d, Y') ?? 'Not yet acknowledged' }}</dd></div>
                <div><dt>Serial numbers released</dt><dd>{{ $application->serials_released_at?->format('F d, Y') ?? 'Not yet released' }}</dd></div>
                <div><dt>Last updated by</dt><dd>{{ $application->updater?->name ?? $application->creator?->name ?? 'System' }}</dd></div>
            </dl>
            @if($application->notes)<div class="ched-latest-note"><span>Latest note</span><p>{{ $application->notes }}</p></div>@endif
            <div class="ched-report-reminder"><span aria-hidden="true">▤</span><div><strong>Need to inspect or regenerate the workbook?</strong><p>Use the same academic year and semester in Reports Center.</p></div><a href="{{ route($routePrefix.'.reports.index', ['type' => 'ched_semestral', 'academic_year' => $application->academic_year, 'semester' => $application->semester]) }}">Open report →</a></div>
        </section>

        <section class="card ched-status-form-card">
            <div class="section-heading"><div><span class="eyebrow">Record the next event</span><h3>Update tracking status</h3><p>Only save events that already happened through email or CHED correspondence.</p></div></div>
            @if($nextStatuses)
                <form method="POST" action="{{ route($routePrefix.'.ched-applications.status', $application) }}" class="stack-form ched-status-form">
                    @csrf @method('PUT')
                    <label class="field-group"><span>New status</span><select name="status" required>@foreach($nextStatuses as $value => $label)<option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Date of event</span><input type="date" name="status_date" value="{{ old('status_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                    <label class="field-group"><span>CHED recipient email</span><input type="email" name="submission_email" value="{{ old('submission_email', $application->submission_email) }}" placeholder="Required when recording an emailed submission"></label>
                    <label class="field-group"><span>CHED acknowledgment/reference</span><input name="ched_reference" value="{{ old('ched_reference', $application->ched_reference) }}" placeholder="Reference from the email thread or CHED"></label>
                    <label class="field-group"><span>What happened?</span><textarea name="notes" rows="5" required placeholder="Summarize the response, correction requested, approval, or follow-up action">{{ old('notes') }}</textarea></label>
                    <button class="primary-button" type="submit">Save tracker update</button>
                </form>
            @else
                <div class="empty-state ched-closed-state"><strong>Tracking is complete</strong><span>No additional workflow status is available for this record.</span></div>
            @endif
        </section>
    </div>

    <section class="card user-table-card ched-history-card">
        <div class="section-heading"><div><span class="eyebrow">Accountability</span><h3>Status history</h3><p>Every manual update is retained with its date, note, and administrator.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Status change</th><th>Notes</th><th>Recorded by</th></tr></thead><tbody>
            @forelse($application->histories as $history)
                <tr><td>{{ $history->occurred_at->format('M d, Y') }}<small class="ched-history-time">{{ $history->occurred_at->format('g:i A') }}</small></td><td><span class="ched-history-status">{{ $history->from_status ? (\App\Models\ChedApplication::STATUSES[$history->from_status] ?? str($history->from_status)->headline()).' → ' : '' }}{{ \App\Models\ChedApplication::STATUSES[$history->to_status] ?? str($history->to_status)->headline() }}</span></td><td>{{ $history->notes }}</td><td>{{ $history->changedBy?->name ?? 'System' }}</td></tr>
            @empty
                <tr><td colspan="4"><div class="empty-state"><strong>No status history yet</strong><span>The first recorded event will appear here.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
