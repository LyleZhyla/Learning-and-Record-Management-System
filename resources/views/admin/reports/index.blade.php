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
            <p>{{ $isFacilitatorReport ? 'Review student, attendance, grade, and section data limited to the sections assigned to you.' : ($isCoordinatorReport ? 'Review student, attendance, grade, and section data limited to your assigned component.' : 'Review institution-wide student, attendance, grade, component, and section data.') }} Apply filters before printing or downloading a Word, Excel, or PDF file.</p>
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

    <section class="report-analytics-section" aria-labelledby="report-analytics-title">
        <div class="report-analytics-heading">
            <div><span class="eyebrow">Filtered analytics</span><h3 id="report-analytics-title">Attendance and enrollment overview</h3><p>The graphs follow the academic year, semester, component, section, and date filters above.</p></div>
        </div>
        <div class="report-analytics-grid">
            <article class="card report-chart-card">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Attendance trend</span><h4>Daily attendance rate</h4><p>Present and late records across the latest 12 attendance dates.</p></div>
                    <span class="report-chart-total"><strong>{{ number_format($attendanceChart['average_rate'], 1) }}%</strong><small>Average rate</small></span>
                </div>
                @if($attendanceChart['points']->isNotEmpty())
                    <div class="attendance-line-chart">
                        <svg viewBox="0 0 {{ $attendanceChart['width'] }} {{ $attendanceChart['height'] }}" role="img" aria-labelledby="attendance-chart-title attendance-chart-description">
                            <title id="attendance-chart-title">Attendance rate line graph</title>
                            <desc id="attendance-chart-description">Daily percentage of present and late attendance records for the selected report scope.</desc>
                            @foreach($attendanceChart['ticks'] as $tick)
                                <line class="attendance-grid-line" x1="{{ $attendanceChart['left'] }}" y1="{{ $tick['y'] }}" x2="{{ $attendanceChart['right'] }}" y2="{{ $tick['y'] }}" />
                                <text class="attendance-axis-label" x="{{ $attendanceChart['left'] - 10 }}" y="{{ $tick['y'] + 4 }}" text-anchor="end">{{ $tick['value'] }}%</text>
                            @endforeach
                            <polygon class="attendance-area" points="{{ $attendanceChart['area_points'] }}" />
                            <polyline class="attendance-line" points="{{ $attendanceChart['point_string'] }}" />
                            @foreach($attendanceChart['points'] as $point)
                                <circle class="attendance-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5">
                                    <title>{{ $point['label'] }}: {{ number_format($point['rate'], 1) }}% ({{ $point['attended'] }} of {{ $point['total'] }})</title>
                                </circle>
                                <text class="attendance-date-label" x="{{ $point['x'] }}" y="238" text-anchor="middle">{{ $point['label'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                @else
                    <div class="empty-state report-chart-empty"><strong>No attendance data yet</strong><span>Attendance trends will appear after records are created within this scope.</span></div>
                @endif
            </article>

            <article class="card report-chart-card">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Enrollment distribution</span><h4>Enrollees per component</h4><p>Unique enrolled students within the selected academic scope.</p></div>
                    <span class="report-chart-total green"><strong>{{ number_format($enrollmentTotal) }}</strong><small>Total enrollees</small></span>
                </div>
                @if($enrollmentBreakdown->isNotEmpty())
                    <div class="report-bar-chart" role="list" aria-label="Bar graph of enrollees per NSTP component" style="--report-bar-columns: {{ max(1, $enrollmentBreakdown->count()) }}">
                        @foreach($enrollmentBreakdown as $component)
                            <article class="report-bar-column" role="listitem" aria-label="{{ $component['code'] }}: {{ number_format($component['count']) }} enrollees">
                                <div class="report-bar-track" aria-hidden="true"><span class="component-{{ strtolower($component['code']) }}" style="height: {{ $component['percentage'] }}%"><b>{{ number_format($component['count']) }}</b></span></div>
                                <div class="report-bar-label"><strong>{{ $component['code'] }}</strong><small>{{ $component['name'] }}</small></div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state report-chart-empty"><strong>No enrollment data yet</strong><span>Enrollment totals will appear when components are available within this scope.</span></div>
                @endif
            </article>
        </div>
    </section>

    <section class="card user-table-card report-result-card">
        <div class="report-result-heading">
            <div><span class="eyebrow">Generated report</span><h3>{{ $report['title'] }}</h3><p>{{ $report['rows']->count() }} record{{ $report['rows']->count() === 1 ? '' : 's' }} matched the selected filters.</p></div>
            <div class="report-output-actions">
                <a class="report-print-action" target="_blank" href="{{ route($routePrefix.'.reports.print', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">
                    <span class="report-output-icon" aria-hidden="true">⎙</span>
                    <span><strong>Print report</strong><small>Printer-friendly preview</small></span>
                    <span class="report-action-arrow" aria-hidden="true">↗</span>
                </a>
                <div
                    class="report-save-control report-download-panel"
                    data-report-download
                    data-pdf-url="{{ route($routePrefix.'.reports.pdf', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}"
                    data-word-url="{{ route($routePrefix.'.reports.document', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}"
                    data-excel-url="{{ route($routePrefix.'.reports.export', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}"
                    data-base-filename="{{ str($report['title'])->slug() }}"
                >
                    <div class="report-download-heading">
                        <span class="report-output-icon" aria-hidden="true">↓</span>
                        <span><strong>Download report</strong><small>Choose format and columns</small></span>
                    </div>
                    <label class="report-format-field">
                        <span>File format</span>
                        <select data-report-format aria-label="Select download file format">
                            <option value="pdf">PDF document</option>
                            <option value="docx">Word document with official NSTP template</option>
                            <option value="xlsx">Excel workbook</option>
                        </select>
                    </label>
                    <details class="report-field-selector" data-report-fields>
                        <summary>
                            <span><strong>Choose data to include</strong><small>Customize downloaded columns</small></span>
                            <b data-report-field-count>{{ count($report['headers']) }} selected</b>
                        </summary>
                        <fieldset>
                            <legend>Downloadable data</legend>
                            <div class="report-field-toolbar">
                                <span>Downloadable data</span>
                                <div>
                                    <button type="button" data-report-fields-all>Select all</button>
                                    <button type="button" data-report-fields-clear>Clear all</button>
                                </div>
                            </div>
                            <div class="report-field-grid">
                                @foreach($report['headers'] as $columnIndex => $header)
                                    <label><input type="checkbox" value="{{ $columnIndex }}" checked data-report-field> <span>{{ $header }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                    </details>
                    <div class="report-save-footer">
                        <button class="primary-button compact" type="button" data-report-save><span aria-hidden="true">↓</span><span data-report-save-label>Save PDF report</span></button>
                        <small class="report-save-status" data-report-save-status aria-live="polite">PDF · {{ count($report['headers']) }} fields selected. Choose the save folder next.</small>
                    </div>
                    <noscript>
                        <a href="{{ route($routePrefix.'.reports.pdf', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download PDF</a>
                        <a href="{{ route($routePrefix.'.reports.document', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download Word</a>
                        <a href="{{ route($routePrefix.'.reports.export', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download Excel</a>
                    </noscript>
                </div>
            </div>
        </div>
        @if(array_key_exists('groups', $report) && $report['groups']->isNotEmpty())
            @foreach($report['groups'] as $group)
                <div class="report-section-heading"><div><span class="eyebrow">Section group</span><h4>{{ $group['title'] }}</h4><p>{{ $group['subtitle'] }}</p></div><strong>{{ $group['rows']->count() }} student{{ $group['rows']->count() === 1 ? '' : 's' }}</strong></div>
                <div class="table-wrap"><table class="data-table report-table"><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($group['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></div>
            @endforeach
        @else
            <div class="table-wrap"><table class="data-table report-table"><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse($report['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['headers']) }}"><div class="empty-state"><strong>No records found</strong><span>Try removing one or more report filters.</span></div></td></tr>@endforelse</tbody></table></div>
        @endif
    </section>
    <script src="{{ asset('js/report-download.js') }}?v={{ filemtime(public_path('js/report-download.js')) }}"></script>
@endsection
