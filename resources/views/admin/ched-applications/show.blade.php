@extends($layout)

@section('title', $application->reference_number)
@section('page-title', 'CHED Application Tracker')

@section('content')
    <section class="welcome-banner">
        <div><span class="eyebrow">{{ $application->reference_number }}</span><h2>{{ $application->statusLabel() }}</h2><p>{{ $application->academic_year }} · {{ \App\Models\NstpSection::SEMESTERS[$application->semester] ?? str($application->semester)->headline() }} · {{ $application->student_count }} eligible CWTS/LTS student{{ $application->student_count === 1 ? '' : 's' }} when this record was created.</p></div>
        <span class="welcome-icon" aria-hidden="true">✓</span>
    </section>

    @if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="card" style="margin-bottom: 1.25rem">
        <div class="section-heading"><div><span class="eyebrow">Official workbook</span><h3>Prepare file for manual email</h3><p>The system only generates the workbook. Download it, check it, and email it to CHED outside this application. The NSTP serial-number column intentionally stays blank because CHED assigns those numbers.</p></div></div>
        <div style="padding: 0 1.25rem 1.25rem; display: flex; gap: .75rem; flex-wrap: wrap">
            <a class="primary-button" href="{{ route($routePrefix.'.ched-applications.workbook', $application) }}">Download CHED workbook</a>
            <a class="secondary-button" href="{{ route($routePrefix.'.reports.index', ['type' => 'ched_semestral', 'academic_year' => $application->academic_year, 'semester' => $application->semester]) }}">Preview eligible records</a>
            <a class="secondary-button" href="{{ route($routePrefix.'.ched-applications.index') }}">Back to applications</a>
        </div>
    </section>

    <div class="dashboard-grid two-column" style="margin-bottom: 1.25rem">
        <section class="card"><div class="section-heading"><div><span class="eyebrow">Recorded details</span><h3>Application summary</h3></div></div><div style="padding: 0 1.25rem 1.25rem">
            <p><strong>Submission email:</strong> {{ $application->submission_email ?: 'Not recorded' }}</p>
            <p><strong>CHED acknowledgment/reference:</strong> {{ $application->ched_reference ?: 'Not recorded' }}</p>
            <p><strong>Workbook prepared:</strong> {{ $application->prepared_at?->format('M d, Y g:i A') ?? 'Not yet' }}</p>
            <p><strong>Manually emailed:</strong> {{ $application->submitted_at?->format('M d, Y g:i A') ?? 'Not yet' }}</p>
            <p><strong>Serial numbers released by CHED:</strong> {{ $application->serials_released_at?->format('M d, Y') ?? 'Not yet' }}</p>
            @if($application->notes)<p><strong>Latest notes:</strong><br>{{ $application->notes }}</p>@endif
        </div></section>

        <section class="card"><div class="section-heading"><div><span class="eyebrow">Next step</span><h3>Update tracking status</h3><p>Record only actions that already happened outside the system.</p></div></div><div style="padding: 0 1.25rem 1.25rem">
            @if($nextStatuses)
                <form method="POST" action="{{ route($routePrefix.'.ched-applications.status', $application) }}" class="stack-form">
                    @csrf @method('PUT')
                    <label class="field-group"><span>New status</span><select name="status" required>@foreach($nextStatuses as $value => $label)<option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="field-group"><span>Date of action/status</span><input type="date" name="status_date" value="{{ old('status_date', now()->toDateString()) }}" required></label>
                    <label class="field-group"><span>CHED email used</span><input type="email" name="submission_email" value="{{ old('submission_email', $application->submission_email) }}" placeholder="Required when marking as emailed"></label>
                    <label class="field-group"><span>CHED acknowledgment/reference</span><input name="ched_reference" value="{{ old('ched_reference', $application->ched_reference) }}" placeholder="Optional CHED-provided reference"></label>
                    <label class="field-group"><span>Tracking notes</span><textarea name="notes" rows="4" required placeholder="What happened, feedback received, or action required">{{ old('notes') }}</textarea></label>
                    <button class="primary-button" type="submit">Save status update</button>
                </form>
            @else
                <div class="empty-state"><strong>Tracking is closed</strong><span>No further workflow status is available.</span></div>
            @endif
        </div></section>
    </div>

    <section class="card user-table-card"><div class="section-heading"><div><span class="eyebrow">Audit trail</span><h3>Complete status history</h3></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Status change</th><th>Notes</th><th>Recorded by</th></tr></thead><tbody>
        @foreach($application->histories as $history)<tr><td>{{ $history->occurred_at->format('M d, Y g:i A') }}</td><td>{{ $history->from_status ? (\App\Models\ChedApplication::STATUSES[$history->from_status] ?? str($history->from_status)->headline()).' → ' : '' }}{{ \App\Models\ChedApplication::STATUSES[$history->to_status] ?? str($history->to_status)->headline() }}</td><td>{{ $history->notes }}</td><td>{{ $history->changedBy?->name ?? 'System' }}</td></tr>@endforeach
    </tbody></table></div></section>
@endsection
