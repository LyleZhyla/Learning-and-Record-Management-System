@extends($layout)

@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
    @php($isScopedReport = $isCoordinatorReport || $isFacilitatorReport)
    @php($publicFilters = collect($filters)->except('facilitator_id')->all())
    <section class="welcome-banner report-welcome">
        <div>
            <span class="eyebrow">{{ $isFacilitatorReport ? 'Assigned section reporting center' : ($isCoordinatorReport ? 'Assigned component reporting center' : 'Central reporting center') }}</span>
            <h2>{{ $isFacilitatorReport ? 'My section reports' : ($isCoordinatorReport ? $reportScope.' operational reports' : 'Operational reports, ready when needed.') }}</h2>
            <p>{{ $isFacilitatorReport ? 'Review student, attendance, grade, and section data limited to the sections assigned to you.' : ($isCoordinatorReport ? 'Review student, attendance, grade, and section data limited to your assigned component.' : 'Review institution-wide student, attendance, grade, component, and section data.') }} Apply filters before printing or downloading a CSV file.</p>
        </div>
        <span class="workspace-date">Last generated<strong>{{ $report['generated_at']->format('M d, Y · h:i A') }}</strong></span>
    </section>

    <section class="metric-grid" aria-label="Reporting overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>{{ $isFacilitatorReport ? 'MY STUDENTS' : ($isCoordinatorReport ? 'COMPONENT STUDENTS' : 'REGISTERED STUDENTS') }}</small><strong>{{ $metrics['students'] }}</strong><p>{{ $isFacilitatorReport ? 'Students in assigned sections' : ($isCoordinatorReport ? $reportScope.' enrolled students' : 'System-wide accounts') }}</p></div></article>
        <article class="metric-card"><span class="metric-icon green">✓</span><div><small>ATTENDANCE RATE</small><strong>{{ number_format($metrics['attendance_rate'], 1) }}%</strong><p>Present and late records</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">◎</span><div><small>GRADED SUBMISSIONS</small><strong>{{ $metrics['graded'] }}</strong><p>Verified assessment results</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">▦</span><div><small>{{ $isFacilitatorReport ? 'MY SECTIONS' : 'NSTP SECTIONS' }}</small><strong>{{ $metrics['sections'] }}</strong><p>{{ $isScopedReport ? 'Assigned academic coverage' : 'All academic terms' }}</p></div></article>
    </section>

    <nav class="report-tabs" aria-label="Report types">
        @foreach ($reportTypes as $type => $label)
            <a class="{{ $filters['type'] === $type ? 'active' : '' }}" href="{{ route($routePrefix.'.reports.index', array_merge(collect($publicFilters)->except('type')->all(), ['type' => $type])) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <section class="card report-filter-card">
        <form method="GET" action="{{ route($routePrefix.'.reports.index') }}" class="report-filter-grid">
            <input type="hidden" name="type" value="{{ $filters['type'] }}">
            <label class="field-group"><span>Academic year</span><select name="academic_year"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year }}" @selected(($filters['academic_year'] ?? '') === $year)>{{ $year }}</option>@endforeach</select></label>
            <label class="field-group"><span>Semester</span><select name="semester"><option value="">All semesters</option>@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected(($filters['semester'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Component</span><select name="component_id"><option value="">All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected(($filters['component_id'] ?? '') == $component->id)>{{ $component->code }}</option>@endforeach</select></label>
            <label class="field-group"><span>Section</span><select name="section_id"><option value="">All sections</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(($filters['section_id'] ?? '') == $section->id)>{{ $section->code }} · {{ $section->component->code }}</option>@endforeach</select></label>
            @if($filters['type'] === 'attendance')
                <label class="field-group"><span>Date from</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label class="field-group"><span>Date to</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            @endif
            <div class="report-filter-actions"><button class="filter-button" type="submit">Generate report</button><a class="clear-filter" href="{{ route($routePrefix.'.reports.index', ['type' => $filters['type']]) }}">Clear filters</a></div>
        </form>
    </section>

    <section class="card user-table-card report-result-card">
        <div class="report-result-heading">
            <div><span class="eyebrow">Generated report</span><h3>{{ $report['title'] }}</h3><p>{{ $report['rows']->count() }} record{{ $report['rows']->count() === 1 ? '' : 's' }} matched the selected filters.</p></div>
            <div class="report-output-actions">
                <a class="secondary-outline-button" target="_blank" href="{{ route($routePrefix.'.reports.print', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Print report</a>
                <a class="primary-button compact" href="{{ route($routePrefix.'.reports.export', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download CSV</a>
            </div>
        </div>
        <div class="table-wrap"><table class="data-table report-table"><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['headers']) }}"><div class="empty-state"><strong>No records found</strong><span>Try removing one or more report filters.</span></div></td></tr>@endforelse</tbody></table></div>
    </section>
@endsection
