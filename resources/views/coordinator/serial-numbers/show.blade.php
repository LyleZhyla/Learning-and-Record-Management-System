@extends($layout)
@section('title', 'Encode Serial Numbers')
@section('page-title', 'Serial Number Encoding')

@section('content')
<section class="serial-encode-hero">
    <div><a href="{{ route($routePrefix.'.serial-numbers.index', ['academic_year' => $serialNumberRelease->academic_year]) }}">← {{ $serialNumberRelease->academic_year }} registry</a><span class="eyebrow">{{ $serialNumberRelease->component->code }} · {{ $serialNumberRelease->academic_year }}</span><h2>Encode CHED serial numbers</h2><p>{{ \App\Models\NstpSection::SEMESTERS[$serialNumberRelease->semester] ?? str($serialNumberRelease->semester)->headline() }} · Enter each serial exactly as it appears in the official source file.</p></div>
    <a class="secondary-button" href="{{ route($routePrefix.'.serial-numbers.download', $serialNumberRelease) }}">Download source file</a>
</section>

<section class="metric-grid serial-encode-metrics" aria-label="Batch encoding progress">
    <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>QUALIFIED</small><strong>{{ number_format($graduateCount) }}</strong><p>Students in this batch</p></div></article>
    <article class="metric-card"><span class="metric-icon green">✓</span><div><small>ENCODED</small><strong>{{ number_format($encodedCount) }}</strong><p>With saved serial number</p></div></article>
    <article class="metric-card"><span class="metric-icon orange">!</span><div><small>REMAINING</small><strong>{{ number_format(max(0, $graduateCount - $encodedCount)) }}</strong><p>Needs verification and entry</p></div></article>
</section>

<section class="card serial-source-summary">
    <div class="serial-source-heading"><div><span class="eyebrow">Official source record</span><h3>{{ $serialNumberRelease->source_file_original_name }}</h3><p>Keep this file open while encoding. The system validates uniqueness but does not invent or derive serial numbers.</p></div><span class="serial-progress-badge">{{ $graduateCount > 0 ? round(($encodedCount / $graduateCount) * 100) : 0 }}% encoded</span></div>
    <div class="serial-source-details"><span><small>Academic year</small><strong>{{ $serialNumberRelease->academic_year }}</strong></span><span><small>Component</small><strong>{{ $serialNumberRelease->component->code }}</strong></span><span><small>Date received</small><strong>{{ $serialNumberRelease->received_at->format('M d, Y') }}</strong></span><span><small>Uploaded by</small><strong>{{ $serialNumberRelease->uploader?->name ?? 'Former coordinator' }}</strong></span></div>
    @if($serialNumberRelease->notes)<div class="serial-source-note"><strong>Source notes</strong><p>{{ $serialNumberRelease->notes }}</p></div>@endif
</section>

<section class="card user-table-card serial-encoding-card">
    <div class="section-heading"><div><span class="eyebrow">Dedicated encoding list</span><h3>Qualified student serial numbers</h3><p>Search a student, verify whether a serial is already present, then encode or correct the official value.</p></div></div>
    <form method="GET" action="{{ route($routePrefix.'.serial-numbers.encode', $serialNumberRelease) }}" class="serial-encoding-filters">
        <label class="field-group"><span>Serial status</span><select name="status"><option value="">All students</option><option value="missing" @selected($selectedStatus === 'missing')>Missing serial number</option><option value="encoded" @selected($selectedStatus === 'encoded')>Already encoded</option></select></label>
        <label class="field-group serial-search-field"><span>Search student or serial number</span><input name="search" value="{{ $search }}" placeholder="Name, student number, email, or serial"></label>
        <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.serial-numbers.encode', $serialNumberRelease) }}">Clear</a></div>
    </form>
    <div class="table-wrap"><table class="data-table serial-encoding-table"><thead><tr><th>#</th><th>Qualified student</th><th>Section</th><th>Verification</th><th>CHED serial number</th><th>Action</th></tr></thead><tbody>
        @forelse($graduates as $enrollment)
            @php($record = $encoded->get($enrollment->id))
            <tr class="{{ $record ? 'serial-row-encoded' : 'serial-row-missing' }}">
                <td>{{ $graduates->firstItem() + $loop->index }}</td>
                <td><strong>{{ $enrollment->student->studentRecordName() }}</strong><small>{{ $enrollment->student->studentProfile?->student_number ?? $enrollment->student->email }}</small></td>
                <td><strong>{{ $enrollment->section?->code }}</strong><small>{{ $serialNumberRelease->component->code }}</small></td>
                <td>@if($record)<span class="serial-verification-state verified"><i>✓</i>Has serial</span>@else<span class="serial-verification-state missing"><i>!</i>Missing</span>@endif</td>
                <td>@if($record)<code class="serial-number-value">{{ $record->serial_number }}</code>@else<span class="muted-cell">No serial number yet</span>@endif</td>
                <td><form method="POST" action="{{ route($routePrefix.'.serial-numbers.students.store', [$serialNumberRelease, $enrollment]) }}" class="serial-inline-form">@csrf @method('PUT')<input name="serial_number" value="{{ old('serial_number', $record?->serial_number) }}" maxlength="100" aria-label="CHED serial number for {{ $enrollment->student->name }}" placeholder="Enter official serial" required><button class="primary-button compact" type="submit">{{ $record ? 'Update' : 'Save' }}</button></form></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><strong>No matching qualified students</strong><span>Adjust the filters, or complete all grading before serial-number encoding.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    @if($graduates->total() > 0)<div class="pagination-row"><span>Showing {{ $graduates->firstItem() }}–{{ $graduates->lastItem() }} of {{ $graduates->total() }}</span>{{ $graduates->links() }}</div>@endif
</section>
@endsection
