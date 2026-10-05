@extends($layout)

@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
    @php($isScopedReport = $isCoordinatorReport || $isFacilitatorReport)
    @php($isChedSemestralReport = $filters['type'] === 'ched_semestral')
    @php($isAfpRotcReport = $filters['type'] === 'afp_rotc_semestral')
    @php($isOfficialSemestralReport = $isChedSemestralReport || $isAfpRotcReport)
    @php($publicFilters = collect($filters)->except('facilitator_id')->all())
    @php($reportCatalog = [
        'Student records' => [
            'description' => 'Enrollment and section-based student lists.',
            'reports' => [
                'students' => 'Complete student enrollment masterlist.',
                'students_by_section' => 'Student lists grouped by NSTP section.',
            ],
        ],
        'Attendance and grading' => [
            'description' => 'Monitoring reports and printable class forms.',
            'reports' => [
                'attendance' => 'Recorded attendance status per session.',
                'grades' => 'Assessment and final grade records.',
                'attendance_sheet' => 'Printable attendance sheet per class.',
                'grade_sheet' => 'Printable grade sheet per class.',
            ],
        ],
        'Official submissions' => [
            'description' => 'Agency-prescribed semestral workbooks.',
            'reports' => [
                'ched_semestral' => 'CHED workbook for passing CWTS and LTS completers.',
                'afp_rotc_semestral' => 'AFP workbook containing all ROTC cadets.',
            ],
        ],
        'Program overview' => [
            'description' => 'NSTP component and section coverage.',
            'reports' => [
                'sections' => 'Component, section, facilitator, and enrollment summary.',
            ],
        ],
    ])
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

    <section class="card report-catalog" aria-labelledby="available-downloads-title">
        <div class="report-catalog-heading">
            <div>
                <span class="eyebrow">Available downloads</span>
                <h3 id="available-downloads-title">Choose the report you need</h3>
                <p>Only reports available for your role and assigned NSTP scope are shown below.</p>
            </div>
            <span class="report-catalog-count"><strong>{{ count($reportTypes) }}</strong> report{{ count($reportTypes) === 1 ? '' : 's' }} available</span>
        </div>
        <div class="report-category-list">
            @foreach($reportCatalog as $categoryName => $category)
                @php($availableReports = collect($category['reports'])->only(array_keys($reportTypes)))
                @if($availableReports->isNotEmpty())
                    <section class="report-category-group" aria-labelledby="report-category-{{ str($categoryName)->slug() }}">
                        <div class="report-category-heading">
                            <h4 id="report-category-{{ str($categoryName)->slug() }}">{{ $categoryName }}</h4>
                            <p>{{ $category['description'] }}</p>
                        </div>
                        <nav class="report-download-grid" aria-label="{{ $categoryName }} downloads">
                            @foreach($availableReports as $type => $description)
                                @php($isSelectedReport = $filters['type'] === $type)
                                <a
                                    class="report-download-option {{ $isSelectedReport ? 'selected' : '' }}"
                                    href="{{ route($routePrefix.'.reports.index', array_merge(collect($publicFilters)->except('type')->all(), ['type' => $type])) }}"
                                    @if($isSelectedReport) aria-current="page" @endif
                                >
                                    <span class="report-download-copy">
                                        <strong>{{ $reportTypes[$type] }}</strong>
                                        <small>{{ $description }}</small>
                                    </span>
                                    <span class="report-download-state">{{ $isSelectedReport ? 'Selected report' : 'View filters' }}</span>
                                </a>
                            @endforeach
                        </nav>
                    </section>
                @endif
            @endforeach
        </div>
    </section>

    <section class="card report-filter-card">
        <div class="report-filter-heading">
            <div><span class="eyebrow">Report filters</span><h3>Filter {{ $reportTypes[$filters['type']] }}</h3><p>Set the reporting period and scope before previewing or downloading this report.</p></div>
            <span class="report-filter-selection">Selected report</span>
        </div>
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
            <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.reports.index', ['type' => $filters['type']]) }}">Clear filters</a></div>
        </form>
    </section>

    <section class="card user-table-card report-result-card">
        <div class="report-result-heading">
            <div><span class="eyebrow">Report preview</span><h3>{{ $report['title'] }}</h3><p>{{ $report['rows']->count() }} record{{ $report['rows']->count() === 1 ? '' : 's' }} matched the selected filters.</p></div>
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
                    data-fixed-columns="{{ $isOfficialSemestralReport ? 'true' : 'false' }}"
                >
                    <div class="report-download-heading">
                        <span class="report-output-icon" aria-hidden="true">↓</span>
                        <span><strong>Download report</strong><small>{{ $isOfficialSemestralReport ? 'Official workbook format' : 'Choose format and columns' }}</small></span>
                    </div>
                    <label class="report-format-field">
                        <span>File format</span>
                        <select data-report-format aria-label="Select download file format">
                            @if($isOfficialSemestralReport)
                                <option value="xlsx">{{ $isChedSemestralReport ? 'CHED' : 'AFP ROTC' }} Excel workbook</option>
                            @else
                                <option value="pdf">PDF document</option>
                                <option value="docx">Word document with official NSTP template</option>
                                <option value="xlsx">Excel workbook</option>
                            @endif
                        </select>
                    </label>
                    @if($isOfficialSemestralReport)
                        <p class="form-help">@if($isChedSemestralReport)Uses the official CHED workbook layout. Only completed, passing CWTS and LTS students are included. NSTP serial number cells remain blank for assignment.@else Uses the AFP ROTC grade-report layout. All enrolled ROTC cadets are included, including failed, incomplete, and ungraded records.@endif</p>
                    @else
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
                    @endif
                    <div class="report-save-footer">
                        <button class="primary-button compact" type="button" data-report-save><span aria-hidden="true">↓</span><span data-report-save-label>Save {{ $isOfficialSemestralReport ? 'XLSX' : 'PDF' }} report</span></button>
                        <small class="report-save-status" data-report-save-status aria-live="polite">{{ $isOfficialSemestralReport ? 'XLSX · Official semestral layout. Choose the save folder next.' : 'PDF · '.count($report['headers']).' fields selected. Choose the save folder next.' }}</small>
                    </div>
                    <noscript>
                        @unless($isOfficialSemestralReport)
                            <a href="{{ route($routePrefix.'.reports.pdf', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download PDF</a>
                            <a href="{{ route($routePrefix.'.reports.document', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download Word</a>
                        @endunless
                        <a href="{{ route($routePrefix.'.reports.export', array_merge(['type' => $filters['type']], collect($publicFilters)->except('type')->all())) }}">Download Excel</a>
                    </noscript>
                </div>
            </div>
        </div>
        @if(array_key_exists('groups', $report) && $report['groups']->isNotEmpty())
            @foreach($preview->getCollection()->groupBy('group_index') as $groupRows)
                @php($group = $groupRows->first())
                <div class="report-section-heading"><div><span class="eyebrow">Section group</span><h4>{{ $group['group_title'] }}</h4><p>{{ $group['group_subtitle'] }}</p></div><strong>{{ $group['group_total'] }} student{{ $group['group_total'] === 1 ? '' : 's' }}</strong></div>
                <div class="table-wrap"><table class="data-table report-table"><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($groupRows as $item)<tr>@foreach($item['row'] as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></div>
            @endforeach
        @else
            <div class="table-wrap"><table class="data-table report-table"><thead><tr>@foreach($report['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse($preview as $item)<tr>@foreach($item['row'] as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['headers']) }}"><div class="empty-state"><strong>No records found</strong><span>Try removing one or more report filters.</span></div></td></tr>@endforelse</tbody></table></div>
        @endif
        @if($preview->hasPages())<div class="pagination-row"><span>Showing {{ $preview->firstItem() }}–{{ $preview->lastItem() }} of {{ $preview->total() }} records</span>{{ $preview->links() }}</div>@endif
    </section>
    <script src="{{ asset('js/report-download.js') }}?v={{ filemtime(public_path('js/report-download.js')) }}"></script>
@endsection
