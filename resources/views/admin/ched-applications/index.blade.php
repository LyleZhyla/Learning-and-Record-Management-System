@extends($layout)

@section('title', 'CHED Application Tracker')
@section('page-title', 'CHED Application Tracker')

@section('content')
    <section class="ched-tracker-hero">
        <div>
            <span class="eyebrow">Manual email monitoring</span>
            <h2>Track what happens after the CHED report is sent.</h2>
            <p>The official workbook is prepared in Reports Center. This page only records the manual email submission, CHED feedback, approval, and serial-number release.</p>
        </div>
        <a class="secondary-button" href="{{ route($routePrefix.'.reports.index', ['type' => 'ched_semestral', 'academic_year' => $defaultAcademicYear, 'semester' => $defaultSemester]) }}">Open CHED report <span aria-hidden="true">→</span></a>
    </section>

    <section class="ched-workflow" aria-label="CHED submission workflow">
        <article><span>1</span><div><small>Reports Center</small><strong>Generate and verify</strong><p>Download the official CHED workbook with the correct term filters.</p></div></article>
        <article><span>2</span><div><small>Institutional email</small><strong>Send outside SNAPIE</strong><p>Email the verified workbook to the appropriate CHED office.</p></div></article>
        <article class="current"><span>3</span><div><small>Application Tracker</small><strong>Record and follow up</strong><p>Log the sent email, then update this record whenever CHED responds.</p></div></article>
    </section>

    @if($errors->any())<div class="alert danger"><strong>Please review the highlighted details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="metric-grid ched-tracker-metrics" aria-label="CHED tracker overview">
        <article class="metric-card"><span class="metric-icon blue">▤</span><div><small>TRACKING RECORDS</small><strong>{{ number_format($trackerMetrics['total']) }}</strong><p>All recorded submissions</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">↗</span><div><small>IN PROGRESS</small><strong>{{ number_format($trackerMetrics['in_progress']) }}</strong><p>Waiting for completion</p></div></article>
        <article class="metric-card"><span class="metric-icon green">✓</span><div><small>COMPLETED</small><strong>{{ number_format($trackerMetrics['completed']) }}</strong><p>Approved, released, or closed</p></div></article>
    </section>

    <div class="ched-tracker-layout">
        <section class="card ched-email-log-card">
            <div class="section-heading">
                <div><span class="eyebrow">After sending the email</span><h3>Record an emailed submission</h3><p>Create this record only after the official workbook has been emailed to CHED.</p></div>
                <span class="ched-form-badge">No file upload</span>
            </div>
            <form method="POST" action="{{ route($routePrefix.'.ched-applications.store') }}" class="ched-email-log-form">
                @csrf
                <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ old('academic_year', $defaultAcademicYear) }}" required placeholder="2026-2027"></label>
                <label class="field-group"><span>Semester</span><select name="semester" required>@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected(old('semester', $defaultSemester) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="field-group"><span>Date emailed</span><input type="date" name="status_date" value="{{ old('status_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
                <label class="field-group"><span>CHED recipient email</span><input type="email" name="submission_email" value="{{ old('submission_email') }}" required placeholder="regional-office@example.gov.ph"></label>
                <label class="field-group full"><span>Email thread or CHED reference <small>Optional</small></span><input name="ched_reference" value="{{ old('ched_reference') }}" placeholder="Subject line, email thread ID, or CHED reference"></label>
                <label class="field-group full"><span>Submission notes</span><textarea name="notes" rows="4" required placeholder="Who sent the email, what was attached, and any follow-up details">{{ old('notes') }}</textarea></label>
                <div class="ched-form-footer"><p><strong>Before saving:</strong> Confirm that the workbook was already sent. Saving does not send an email to CHED.</p><button class="primary-button" type="submit">Record as emailed</button></div>
            </form>
        </section>

        <aside class="card ched-source-card">
            <span class="ched-source-icon" aria-hidden="true">↓</span>
            <span class="eyebrow">Where is the file?</span>
            <h3>Use the CHED Semestral Report</h3>
            <p>The submission file is generated from the Reports Center—not from this tracker.</p>
            <ol><li>Select the academic year and semester.</li><li>Review eligible CWTS/LTS records.</li><li>Download the official Excel workbook.</li><li>Email it manually, then return here.</li></ol>
            <a class="secondary-outline-button" href="{{ route($routePrefix.'.reports.index', ['type' => 'ched_semestral', 'academic_year' => $defaultAcademicYear, 'semester' => $defaultSemester]) }}">Go to Reports Center →</a>
        </aside>
    </div>

    <section class="card user-table-card ched-records-card">
        <div class="section-heading"><div><span class="eyebrow">Recorded submissions</span><h3>CHED follow-up records</h3><p>Open a record to log acknowledgments, corrections, approval, or serial-number release.</p></div></div>
        <form method="GET" class="report-filter-grid ched-record-filters">
            <label class="field-group"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Academic year</span><select name="academic_year"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year }}" @selected($selectedAcademicYear === $year)>{{ $year }}</option>@endforeach</select></label>
            <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.ched-applications.index') }}">Clear</a></div>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Reference</th><th>Term</th><th>Eligible</th><th>Status</th><th>Emailed</th><th>Last updated</th><th></th></tr></thead><tbody>
            @forelse($applications as $application)
                <tr><td><strong>{{ $application->reference_number }}</strong><small class="ched-record-owner">by {{ $application->creator?->name ?? 'System' }}</small></td><td>{{ $application->academic_year }}<br><small>{{ \App\Models\NstpSection::SEMESTERS[$application->semester] ?? str($application->semester)->headline() }}</small></td><td>{{ number_format($application->student_count) }} students</td><td><span class="ched-status-badge status-{{ $application->status }}"><i></i>{{ $application->statusLabel() }}</span></td><td>{{ $application->submitted_at?->format('M d, Y') ?? 'Legacy draft' }}</td><td>{{ $application->updated_at->diffForHumans() }}</td><td><a class="table-action" href="{{ route($routePrefix.'.ched-applications.show', $application) }}">Open tracker →</a></td></tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><strong>No email submissions recorded</strong><span>Download the workbook from Reports, email it to CHED, then record that action above.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($applications->total() > 0)<div class="pagination-row"><span>Showing {{ $applications->count() }} of {{ $applications->total() }}</span>{{ $applications->links() }}</div>@endif
    </section>
@endsection
