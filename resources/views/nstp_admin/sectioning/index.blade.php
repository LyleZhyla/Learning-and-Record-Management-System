@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')
@section('title', 'Sectioning')
@section('page-title', 'Sectioning')

@section('content')
@if($components->isNotEmpty())
<section class="card automated-sectioning-card sectioning-primary-card">
    <div class="automated-sectioning-copy">
        <span class="eyebrow">Automatic sectioning</span>
        <h2>{{ $showAllComponents ? (int) $unsectionedCounts->sum() : (int) ($unsectionedCounts[$componentId] ?? 0) }} student(s) awaiting placement</h2>
        <p>@if($showAllComponents)All active NSTP components and their sections are shown below. Automatic sectioning will process CWTS, LTS, and ROTC in one run.@else Select the component and term. Assigned students will fill available seats first, and new sections will be created only when needed.@endif</p>
    </div>

    <form method="GET" action="{{ route($routePrefix.'.sections.index') }}" class="term-form sectioning-workspace-form sectioning-inline-filter">
        <label class="field-group"><span>NSTP component</span><select name="component_id" required><option value="all" @selected($showAllComponents)>All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected(! $showAllComponents && $componentId === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
        <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ $academicYear }}" pattern="\d{4}-\d{4}" placeholder="2026-2027" required></label>
        <label class="field-group"><span>Semester</span><select name="semester" required>@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected($semester === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="filter-button" type="submit">Apply</button>
    </form>

    <form method="POST" action="{{ route($routePrefix.'.sectioning.automate') }}" class="sectioning-run-form">
        @csrf
        <input type="hidden" name="component_id" value="{{ $showAllComponents ? 'all' : $componentId }}">
        <input type="hidden" name="academic_year" value="{{ $academicYear }}">
        <input type="hidden" name="semester" value="{{ $semester }}">
        <button class="primary-button compact" type="submit">{{ $showAllComponents ? 'Run automatic sectioning for all components' : 'Run automatic sectioning' }}</button>
    </form>
</section>

<section class="sectioning-sections-step">
    <div class="sectioning-list-heading sectioning-main-list-heading">
        <div><span class="eyebrow">Sections</span><strong>{{ $showAllComponents ? 'All component sections' : $selectedComponent->code.' sections' }}</strong><span>{{ $sections->count() }} total</span></div>
        <div class="sectioning-list-actions"><small>{{ $academicYear }} · {{ \App\Models\NstpSection::SEMESTERS[$semester] }}</small><a class="secondary-outline-button" href="{{ route($routePrefix.'.sections.create', $selectedComponent ? ['component' => $componentId] : []) }}">+ Create section</a></div>
    </div>
    <div class="section-summary-grid">
        @forelse($sections as $section)
            <article class="section-summary-card"><div><span class="role-badge role-student">{{ $section->component->code }}</span><span class="status-badge {{ $section->status }}"><i></i>{{ ucfirst($section->status) }}</span></div><h3>{{ $section->code }}</h3><p>{{ $section->name }}</p><div class="section-facilitator"><span>Facilitator</span><strong>{{ $section->facilitator?->name ?? 'Unassigned' }}</strong></div><div class="occupancy-track"><span style="width:{{ min(100, $section->capacity ? $section->enrollments_count / $section->capacity * 100 : 0) }}%"></span></div><small>{{ $section->enrollments_count }} of {{ $section->capacity }} seats</small><a class="table-action section-manage-link" href="{{ route($routePrefix.'.sections.edit', $section) }}">Manage section →</a></article>
        @empty
            <article class="section-summary-card empty-summary"><strong>No sections found</strong><span>{{ $showAllComponents ? 'No sections exist for any active component in this term.' : 'Create a section manually or run automatic sectioning after assigning students from Student Accounts.' }}</span></article>
        @endforelse
    </div>
</section>
@else
<section class="card empty-state sectioning-no-component"><strong>No active NSTP component is available</strong><span>Activate at least one component before using automatic sectioning.</span></section>
@endif
@endsection
