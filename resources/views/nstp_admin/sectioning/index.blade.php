@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')
@section('title', 'Sections & Sectioning')
@section('page-title', 'Sections & Sectioning')

@section('content')
<section class="page-actions sectioning-page-heading">
    <div>
        <span class="eyebrow">Sections and placement</span>
        <h2>Manage NSTP sections</h2>
        <p>Choose a component and term, then create sections manually or place assigned students automatically.</p>
    </div>
</section>

<nav class="sectioning-flow-guide" aria-label="Sectioning steps">
    <a href="#workspace" class="sectioning-flow-item"><span>1</span><div><strong>Choose workspace</strong><small>Component and term</small></div></a>
    <a href="#sections" class="sectioning-flow-item"><span>2</span><div><strong>Build sections</strong><small>Manual or automatic</small></div></a>
</nav>

<section id="workspace" class="card sectioning-step-card">
    <div class="sectioning-step-heading">
        <span class="sectioning-step-number">1</span>
        <div><span class="eyebrow">Workspace selection</span><h3>Choose component and term</h3><p>All section creation and automatic placement actions will apply only to this selection.</p></div>
    </div>
    <form method="GET" action="{{ route($routePrefix.'.sections.index') }}" class="term-form sectioning-workspace-form">
        <label class="field-group"><span>NSTP component</span><select name="component_id" required>@foreach($components as $component)<option value="{{ $component->id }}" @selected($componentId === $component->id)>{{ $component->code }} — {{ $component->name }}</option>@endforeach</select></label>
        <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ $academicYear }}" pattern="\d{4}-\d{4}" placeholder="2026-2027" required></label>
        <label class="field-group"><span>Semester</span><select name="semester" required>@foreach(\App\Models\NstpSection::SEMESTERS as $value => $label)<option value="{{ $value }}" @selected($semester === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="filter-button" type="submit">Load workspace</button>
    </form>
</section>

@if($selectedComponent)
<section class="sectioning-workspace-summary" aria-label="Selected workspace summary">
    <article><span>Selected component</span><strong>{{ $selectedComponent->code }}</strong><small>{{ $selectedComponent->name }}</small></article>
    <article><span>Enrolled students</span><strong>{{ $selectedEnrollmentCount }}</strong><small>{{ $academicYear }} · {{ \App\Models\NstpSection::SEMESTERS[$semester] }}</small></article>
    <article><span>Awaiting section</span><strong>{{ (int) ($unsectionedCounts[$componentId] ?? 0) }}</strong><small>Ready for automatic placement</small></article>
    <article><span>Existing sections</span><strong>{{ $sections->count() }}</strong><small>For this component and term</small></article>
</section>

<details class="card component-settings-disclosure">
    <summary>
        <span class="component-settings-summary"><span class="metric-icon blue">⚙</span><span><strong>Component settings (advanced)</strong><small>Review descriptions, availability, and default section capacity.</small></span></span>
        <span class="component-settings-toggle">{{ $componentSummaries->count() }} components <i>⌄</i></span>
    </summary>
    <div class="component-settings-content">
        @include('nstp_admin.components._cards', ['componentCards' => $componentSummaries, 'showCapacityInfo' => false])
        <div class="information-strip sectioning-capacity-note"><span class="metric-icon blue">i</span><div><strong>How default capacity works</strong><p>Automatic sectioning creates a new section using the component's default capacity only when existing active sections have no available seats. Current assignments are preserved.</p></div></div>
    </div>
</details>

<section id="sections" class="sectioning-sections-step">
    <div class="sectioning-step-heading sectioning-step-title"><span class="sectioning-step-number">2</span><div><span class="eyebrow">Section creation and placement</span><h3>Create and fill {{ $selectedComponent->code }} sections</h3><p>Assign components from Student Accounts, then create sections manually or automatically place students who are awaiting a section.</p></div></div>
    <div class="card automated-sectioning-card">
        <div class="automated-sectioning-copy"><span class="eyebrow">Automatic sectioning</span><h3>{{ (int) ($unsectionedCounts[$componentId] ?? 0) }} student(s) awaiting placement</h3><p>Students will fill available seats in active sections first. A new section is created only when more space is needed.</p></div>
        <div class="sectioning-build-actions">
            <form method="POST" action="{{ route($routePrefix.'.sectioning.automate') }}">@csrf<input type="hidden" name="component_id" value="{{ $componentId }}"><input type="hidden" name="academic_year" value="{{ $academicYear }}"><input type="hidden" name="semester" value="{{ $semester }}"><button class="primary-button compact" type="submit">Run automatic sectioning</button></form>
            <a class="secondary-outline-button" href="{{ route($routePrefix.'.sections.create', ['component' => $componentId]) }}">+ Create section manually</a>
        </div>
    </div>
    <div class="sectioning-list-heading"><div><strong>Sections in this workspace</strong><span>{{ $sections->count() }} total</span></div><small>{{ $selectedComponent->code }} · {{ $academicYear }} · {{ \App\Models\NstpSection::SEMESTERS[$semester] }}</small></div>
    <div class="section-summary-grid">
        @forelse($sections as $section)
            <article class="section-summary-card"><div><span class="role-badge role-student">{{ $section->component->code }}</span><span class="status-badge {{ $section->status }}"><i></i>{{ ucfirst($section->status) }}</span></div><h3>{{ $section->code }}</h3><p>{{ $section->name }}</p><div class="section-facilitator"><span>Facilitator</span><strong>{{ $section->facilitator?->name ?? 'Unassigned' }}</strong></div><div class="occupancy-track"><span style="width:{{ min(100, $section->capacity ? $section->enrollments_count / $section->capacity * 100 : 0) }}%"></span></div><small>{{ $section->enrollments_count }} of {{ $section->capacity }} seats</small><a class="table-action section-manage-link" href="{{ route($routePrefix.'.sections.edit', $section) }}">Manage section →</a></article>
        @empty
            <article class="section-summary-card empty-summary"><strong>No sections for this workspace</strong><span>Create one manually, or assign students from Student Accounts and run automatic sectioning.</span></article>
        @endforelse
    </div>
</section>
@else
<section class="card empty-state sectioning-no-component"><strong>No active NSTP component is available</strong><span>Activate at least one component before creating sections.</span></section>
@endif
@endsection
