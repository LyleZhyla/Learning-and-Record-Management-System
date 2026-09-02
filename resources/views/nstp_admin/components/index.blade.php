@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')

@section('title', 'NSTP Components')
@section('page-title', 'NSTP Components')

@section('content')
    <section class="page-actions component-analytics-heading">
        <div><span class="eyebrow">Enrollment analytics</span><h2>NSTP component insights</h2><p>Compare component enrollment and review student demographics for a selected component and term.</p></div>
        <a class="secondary-outline-button" href="{{ route($routePrefix.'.sections.index') }}">Manage sectioning →</a>
    </section>

    <section class="card component-selection-control {{ $componentSelectionOpen ? 'is-open' : 'is-closed' }}">
        <div class="component-selection-control-copy">
            <span class="component-selection-status-dot" aria-hidden="true"></span>
            <div>
                <span class="eyebrow">Student self-selection</span>
                <h3>Component selection is {{ $componentSelectionOpen ? 'open' : 'closed' }}</h3>
                <p>{{ $componentSelectionOpen ? 'Students without an enrollment can currently choose their component, shirt size, and ROTC details.' : 'Students without an enrollment can view the page, but they cannot submit an NSTP selection.' }}</p>
                <small>Last changed by {{ $componentSelectionSetting?->updater?->name ?? 'System default' }}{{ $componentSelectionSetting?->updated_at ? ' · '.$componentSelectionSetting->updated_at->format('M d, Y g:i A') : '' }}</small>
            </div>
        </div>
        <form method="POST" action="{{ route($routePrefix.'.components.selection-availability') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="is_open" value="{{ $componentSelectionOpen ? 0 : 1 }}">
            <button class="{{ $componentSelectionOpen ? 'danger-button' : 'success-button' }}" type="submit">{{ $componentSelectionOpen ? 'Close selection' : 'Open selection' }}</button>
        </form>
    </section>

    <section class="card component-filter-card">
        <form class="component-analytics-filter" method="GET" action="{{ route($routePrefix.'.components.index') }}" data-component-analytics-filter>
            <label class="field-group"><span>Academic year</span><select name="academic_year">@foreach($academicYears as $year)<option value="{{ $year }}" @selected($academicYear === $year)>{{ $year }}</option>@endforeach</select></label>
            <label class="field-group"><span>Semester</span><select name="semester">@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected($semester === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Specific component</span><select name="component" data-component-select>@foreach($components as $component)<option value="{{ $component->id }}" data-component-code="{{ $component->code }}" @selected($selectedComponent?->id === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
            <label class="field-group" data-ms-level-field @if($selectedComponent?->code !== 'ROTC') hidden @endif><span>ROTC MS level</span><select name="ms_level" data-ms-level-select @disabled($selectedComponent?->code !== 'ROTC')><option value="">All MS levels</option>@foreach($rotcCategories as $value => $label)<option value="{{ $value }}" @selected($msLevel === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="filter-button" type="submit">Apply filters</button>
        </form>
    </section>

    @php
        $largestComponentCount = max(1, (int) $componentEnrollments->max('count'));
        $selectedScope = $selectedComponent?->code.($msLevel ? ' · '.$msLevel : '');
    @endphp
    <section class="card enrollee-chart-card component-total-chart">
        <div class="card-heading">
            <div><span class="eyebrow">All components</span><h3>Enrollees per component</h3><p>{{ $academicYear }} · {{ \App\Models\NstpSection::SEMESTERS[$semester] }}</p></div>
            <span class="enrollee-total"><strong>{{ number_format($componentEnrollments->sum('count')) }}</strong><small>Total enrollees</small></span>
        </div>
        <div class="enrollee-chart" role="list" aria-label="Enrollees per NSTP component" style="--chart-columns: {{ max(1, $componentEnrollments->count()) }}">
            @foreach($componentEnrollments as $component)
                @php($barHeight = $component['count'] > 0 ? max(10, ($component['count'] / $largestComponentCount) * 100) : 0)
                <article class="enrollee-column" role="listitem" aria-label="{{ $component['code'] }}: {{ $component['count'] }} enrollees">
                    <div class="enrollee-column-track" aria-hidden="true"><span class="component-{{ strtolower($component['code']) }}" style="height: {{ $barHeight }}%"><b>{{ number_format($component['count']) }}</b></span></div>
                    <div class="enrollee-column-label"><strong>{{ $component['code'] }}</strong><small>{{ $component['name'] }}</small></div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="analytics-scope-summary" aria-label="Selected analytics scope">
        <div><span>Selected scope</span><strong>{{ $selectedScope }}</strong></div>
        <div><span>Students in scope</span><strong>{{ number_format($selectedEnrollmentCount) }}</strong></div>
        <div><span>Term</span><strong>{{ $academicYear }} · {{ \App\Models\NstpSection::SEMESTERS[$semester] }}</strong></div>
    </section>

    <section class="demographic-chart-grid">
        @foreach(['college' => 'Enrollees per college', 'course' => 'Enrollees per course', 'province' => 'Enrollees per province', 'sex' => 'Enrollees according to sex'] as $key => $title)
            @php($largest = max(1, (int) $breakdowns[$key]->max('count')))
            <article class="card demographic-chart-card">
                <div class="card-heading"><div><span class="eyebrow">{{ $selectedScope }}</span><h3>{{ $title }}</h3><p>Distinct students matching the selected component{{ $msLevel ? ' and MS level' : '' }}.</p></div></div>
                <div class="horizontal-chart" role="list" aria-label="{{ $title }} for {{ $selectedScope }}">
                    @forelse($breakdowns[$key] as $row)
                        <div class="horizontal-chart-row" role="listitem" aria-label="{{ $row['label'] }}: {{ $row['count'] }} enrollees">
                            <div class="horizontal-chart-label"><span title="{{ $row['label'] }}">{{ $row['label'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                            <div class="horizontal-chart-track" aria-hidden="true"><span style="width: {{ $row['count'] > 0 ? max(3, ($row['count'] / $largest) * 100) : 0 }}%"></span></div>
                        </div>
                    @empty
                        <div class="empty-state compact"><strong>No enrollment data</strong><span>No students match the selected filters.</span></div>
                    @endforelse
                </div>
            </article>
        @endforeach
    </section>

    <script src="{{ asset('js/component-analytics.js') }}?v={{ filemtime(public_path('js/component-analytics.js')) }}"></script>
@endsection
