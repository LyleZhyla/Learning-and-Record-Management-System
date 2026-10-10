@extends($layout)

@section('title', 'CHED Applications')
@section('page-title', 'CHED Applications')

@section('content')
    <section class="welcome-banner">
        <div>
            <span class="eyebrow">Manual submission tracker</span>
            <h2>CHED serial-number applications</h2>
            <p>Prepare the official workbook here, send it to CHED through email outside the system, and record every follow-up status. Serial numbers are issued only by CHED.</p>
        </div>
        <span class="welcome-icon" aria-hidden="true">▤</span>
    </section>

    @if($errors->any())<div class="alert alert-error"><strong>Please review the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="card" style="margin-bottom: 1.25rem">
        <div class="section-heading"><div><span class="eyebrow">New tracking record</span><h3>Create CHED application</h3><p>This creates an internal record only. It does not send an email or submit anything to CHED.</p></div></div>
        <form method="POST" action="{{ route($routePrefix.'.ched-applications.store') }}" class="report-filter-grid">
            @csrf
            <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ old('academic_year', $defaultAcademicYear) }}" required placeholder="2026-2027"></label>
            <label class="field-group"><span>Semester</span><select name="semester" required>@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected(old('semester', $defaultSemester) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>CHED submission email (optional until emailed)</span><input type="email" name="submission_email" value="{{ old('submission_email') }}" placeholder="regional-office@example.gov.ph"></label>
            <label class="field-group" style="grid-column: 1 / -1"><span>Initial notes</span><textarea name="notes" rows="3" placeholder="Purpose, preparation notes, or assigned staff">{{ old('notes') }}</textarea></label>
            <div><button class="primary-button" type="submit">Create application record</button></div>
        </form>
    </section>

    <section class="card user-table-card">
        <div class="section-heading"><div><span class="eyebrow">Application records</span><h3>Submission tracking</h3></div></div>
        <form method="GET" class="report-filter-grid" style="padding: 0 1.25rem 1rem">
            <label class="field-group"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Academic year</span><select name="academic_year"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year }}" @selected($selectedAcademicYear === $year)>{{ $year }}</option>@endforeach</select></label>
            <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.ched-applications.index') }}">Clear</a></div>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Internal reference</th><th>Term</th><th>Eligible students</th><th>Status</th><th>Manual submission date</th><th>Updated</th><th></th></tr></thead><tbody>
            @forelse($applications as $application)
                <tr><td><strong>{{ $application->reference_number }}</strong></td><td>{{ $application->academic_year }} · {{ \App\Models\NstpSection::SEMESTERS[$application->semester] ?? str($application->semester)->headline() }}</td><td>{{ $application->student_count }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $application->submitted_at?->format('M d, Y') ?? 'Not yet emailed' }}</td><td>{{ $application->updated_at->diffForHumans() }}</td><td><a href="{{ route($routePrefix.'.ched-applications.show', $application) }}">View tracker →</a></td></tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><strong>No CHED application records yet</strong><span>Create one before preparing the workbook for manual email submission.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($applications->total() > 0)<div class="pagination-row">{{ $applications->links() }}</div>@endif
    </section>
@endsection
