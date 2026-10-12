@extends($layout)
@section('title', 'NSTP Serial Numbers')
@section('page-title', 'NSTP Serial Numbers')

@section('content')
<section class="serial-registry-hero">
    <div><span class="eyebrow">Year-based graduate registry</span><h2>Serial numbers by academic year</h2><p>Select a year to see every qualified student covered by an official release, confirm who already has a serial number, and open the encoding page for missing records.</p></div>
    <a class="secondary-button" href="{{ route('serial-numbers.verify') }}" target="_blank" rel="noopener">Open public verifier ↗</a>
</section>

<section class="card serial-year-selector">
    <div><span class="eyebrow">Registry year</span><h3>{{ $selectedAcademicYear }}</h3><p>All counts, students, and official batches below are limited to this academic year.</p></div>
    <form method="GET" action="{{ route($routePrefix.'.serial-numbers.index') }}">
        @if($isProgramAdministrator && $selectedComponentId)<input type="hidden" name="component_id" value="{{ $selectedComponentId }}">@endif
        <label class="field-group"><span>Academic year</span><select name="academic_year" onchange="this.form.submit()">@foreach($academicYears as $year)<option value="{{ $year }}" @selected($selectedAcademicYear === $year)>{{ $year }}</option>@endforeach</select></label>
    </form>
</section>

<section class="metric-grid serial-registry-metrics" aria-label="Serial number status for {{ $selectedAcademicYear }}">
    <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>QUALIFIED STUDENTS</small><strong>{{ number_format($serialMetrics['eligible']) }}</strong><p>Covered by official batches</p></div></article>
    <article class="metric-card"><span class="metric-icon green">✓</span><div><small>WITH SERIAL NUMBER</small><strong>{{ number_format($serialMetrics['encoded']) }}</strong><p>Encoded and verifiable</p></div></article>
    <article class="metric-card"><span class="metric-icon orange">!</span><div><small>MISSING SERIAL</small><strong>{{ number_format($serialMetrics['missing']) }}</strong><p>Needs encoding</p></div></article>
</section>

<section class="card user-table-card serial-student-registry">
    <div class="section-heading"><div><span class="eyebrow">Student verification list</span><h3>{{ $selectedAcademicYear }} serial-number status</h3><p>Use this list to verify whether a qualified student already has an encoded CHED serial number.</p></div><span class="pill">{{ $studentRows->total() }} student{{ $studentRows->total() === 1 ? '' : 's' }}</span></div>
    <form method="GET" action="{{ route($routePrefix.'.serial-numbers.index') }}" class="serial-registry-filters">
        <input type="hidden" name="academic_year" value="{{ $selectedAcademicYear }}">
        @if($isProgramAdministrator)
            <label class="field-group"><span>Component</span><select name="component_id"><option value="">All components</option>@foreach($components as $availableComponent)<option value="{{ $availableComponent->id }}" @selected($selectedComponentId === $availableComponent->id)>{{ $availableComponent->code }}</option>@endforeach</select></label>
        @endif
        <label class="field-group"><span>Serial status</span><select name="student_status"><option value="">All students</option><option value="encoded" @selected($studentStatus === 'encoded')>With serial number</option><option value="missing" @selected($studentStatus === 'missing')>Missing serial number</option></select></label>
        <label class="field-group serial-search-field"><span>Search student or serial number</span><input name="search" value="{{ $search }}" placeholder="Name, student number, email, or serial"></label>
        <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.serial-numbers.index', ['academic_year' => $selectedAcademicYear]) }}">Clear</a></div>
    </form>
    @include('pagination.top-page-size', ['paginator' => $studentRows, 'title' => 'Students per page'])
    <div class="table-wrap"><table class="data-table serial-registry-table"><thead><tr><th>#</th><th>Student</th><th>Component</th><th>Section / Term</th><th>Serial number</th><th>Verification</th><th></th></tr></thead><tbody>
        @forelse($studentRows as $row)
            @php($enrollment = $row['enrollment']) @php($record = $row['serial']) @php($release = $row['release'])
            <tr>
                <td>{{ $studentRows->firstItem() + $loop->index }}</td>
                <td><strong>{{ $enrollment->student->studentRecordName() }}</strong><small>{{ $enrollment->student->studentProfile?->student_number ?? $enrollment->student->email }}</small></td>
                <td><span class="component-mini-badge component-{{ strtolower($release->component->code) }}">{{ $release->component->code }}</span></td>
                <td><strong>{{ $enrollment->section?->code ?? 'Unassigned' }}</strong><small>{{ $semesters[$release->semester] ?? str($release->semester)->headline() }}</small></td>
                <td>@if($record)<code class="serial-number-value">{{ $record->serial_number }}</code>@else<span class="muted-cell">Not encoded</span>@endif</td>
                <td>@if($record)<span class="serial-verification-state verified"><i>✓</i>Has serial</span>@else<span class="serial-verification-state missing"><i>!</i>Missing</span>@endif</td>
                <td><a class="table-action" href="{{ route($routePrefix.'.serial-numbers.encode', $record?->release ?? $release) }}{{ $record ? '?search='.urlencode($record->serial_number) : '?search='.urlencode($enrollment->student->name) }}">{{ $record ? 'Review' : 'Encode' }} →</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><strong>No students found for {{ $selectedAcademicYear }}</strong><span>Upload an official serial-number release for this year, or adjust the filters.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    @if($studentRows->total() > 0)<div class="pagination-row"><span>Showing {{ $studentRows->firstItem() }}–{{ $studentRows->lastItem() }} of {{ $studentRows->total() }}</span>{{ $studentRows->links() }}</div>@endif
</section>

<div class="serial-management-grid">
    <section class="card serial-upload-card">
        <div class="card-heading"><div><span class="eyebrow">New official release</span><h3>Upload a CHED serial-number list</h3><p>The uploaded file establishes the component, academic year, and semester covered by the encoding page.</p></div></div>
        <form method="POST" action="{{ route($routePrefix.'.serial-numbers.store') }}" enctype="multipart/form-data" class="stack-form">
            @csrf
            @if($isProgramAdministrator)
                <label class="field-group"><span>NSTP component</span><select name="component_id" required><option value="">Select component</option>@foreach($components as $availableComponent)<option value="{{ $availableComponent->id }}" @selected((int) old('component_id', $selectedComponentId) === $availableComponent->id)>{{ $availableComponent->code }} — {{ $availableComponent->name }}</option>@endforeach</select></label>
            @endif
            <div class="form-grid"><label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ old('academic_year', $selectedAcademicYear) }}" pattern="\d{4}-\d{4}" required></label><label class="field-group"><span>Semester</span><select name="semester" required>@foreach($semesters as $value => $label)<option value="{{ $value }}" @selected(old('semester', \App\Models\SystemSetting::studentRegistrationSemester()) === $value)>{{ $label }}</option>@endforeach</select></label></div>
            <label class="field-group"><span>Date received by the office</span><input type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
            <label class="field-group"><span>Official file from CHED</span><input type="file" name="source_file" accept=".pdf,.xlsx,.xls,.csv,.jpg,.jpeg,.png" required><small class="form-help">Private evidence file. Maximum size: 10 MB.</small></label>
            <label class="field-group"><span>Notes <small>Optional</small></span><textarea name="notes" rows="3" maxlength="3000" placeholder="Reference, coverage, or instructions">{{ old('notes') }}</textarea></label>
            <button class="primary-button" type="submit">Upload and open encoding page</button>
        </form>
    </section>

    <section class="card user-table-card serial-batches-card">
        <div class="card-heading"><div><span class="eyebrow">{{ $selectedAcademicYear }} source files</span><h3>Official release batches</h3><p>Each batch opens a dedicated student encoding page.</p></div><span class="pill">{{ $releases->total() }} batch{{ $releases->total() === 1 ? '' : 'es' }}</span></div>
        @include('pagination.top-page-size', ['paginator' => $releases, 'title' => 'Release batches per page'])
        <div class="table-wrap"><table class="data-table"><thead><tr>@if($isProgramAdministrator)<th>Component</th>@endif<th>Semester</th><th>Received</th><th>Source file</th><th>Encoded</th><th></th></tr></thead><tbody>
            @forelse($releases as $release)
                <tr>@if($isProgramAdministrator)<td><strong>{{ $release->component?->code }}</strong></td>@endif<td>{{ $semesters[$release->semester] ?? str($release->semester)->headline() }}</td><td>{{ $release->received_at->format('M d, Y') }}</td><td title="{{ $release->source_file_original_name }}">{{ Str::limit($release->source_file_original_name, 24) }}</td><td>{{ $release->serial_numbers_count }}</td><td><a class="table-action" href="{{ route($routePrefix.'.serial-numbers.encode', $release) }}">Encode list →</a></td></tr>
            @empty
                <tr><td colspan="{{ $isProgramAdministrator ? 6 : 5 }}"><div class="empty-state"><strong>No official release for {{ $selectedAcademicYear }}</strong><span>Upload the received CHED list to create an encoding page.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($releases->total() > 0)<div class="pagination-row"><span>{{ $releases->total() }} batch{{ $releases->total() === 1 ? '' : 'es' }}</span>{{ $releases->links() }}</div>@endif
    </section>
</div>
@endsection
