@extends($routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin')
@section('title','Sections & Sectioning')
@section('page-title','Sections & Student Sectioning')
@section('content')
<section class="page-actions"><div><span class="eyebrow">Sections, enrollment, and placement</span><h2>Manage sections and student assignments</h2><p>Select a term to create or manage sections, enroll students in an NSTP component, and automatically distribute unsectioned students by capacity.</p></div><a class="primary-button compact" href="{{ route($routePrefix.'.sections.create', ['component' => $componentId]) }}">+ Create section</a></section>

<section class="sectioning-component-overview">
    <div class="sectioning-toolbar"><div><span class="eyebrow">NSTP component configuration</span><h3>CWTS, LTS, and ROTC</h3><p class="muted-cell">Review availability, descriptions, default section capacity, and current usage before managing student assignments.</p></div></div>
    @include('nstp_admin.components._cards', ['componentCards' => $componentSummaries, 'componentReturnTo' => 'sectioning'])
</section>

<section class="card term-panel"><form method="GET" action="{{ route($routePrefix.'.sections.index') }}" class="term-form"><label class="field-group"><span>Component</span><select name="component_id"><option value="">All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected($componentId === $component->id)>{{ $component->code }}</option>@endforeach</select></label><label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ $academicYear }}" pattern="\d{4}-\d{4}" required></label><label class="field-group"><span>Semester</span><select name="semester" required>@foreach(\App\Models\NstpSection::SEMESTERS as $value=>$label)<option value="{{ $value }}" @selected($semester === $value)>{{ $label }}</option>@endforeach</select></label><button class="filter-button" type="submit">Load workspace</button></form></section>

<section class="card automated-sectioning-card">
    <div class="automated-sectioning-copy">
        <span class="eyebrow">Automatic sectioning</span>
        <h3>Distribute unsectioned students</h3>
        <p>Available to Super Admin and NSTP Admin for CWTS, LTS, and ROTC. Students are placed into active sections by available capacity, and new sections are created when necessary.</p>
    </div>
    <form class="automated-sectioning-form" method="POST" action="{{ route($routePrefix.'.sectioning.automate') }}">
        @csrf
        <label class="field-group"><span>Component</span><select name="component_id" required><option value="">Select component</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected($componentId === $component->id)>{{ $component->code }} · {{ (int) ($unsectionedCounts[$component->id] ?? 0) }} awaiting section</option>@endforeach</select></label>
        <label class="field-group"><span>Academic year</span><input name="academic_year" value="{{ $academicYear }}" pattern="\d{4}-\d{4}" required></label>
        <label class="field-group"><span>Semester</span><select name="semester" required>@foreach(\App\Models\NstpSection::SEMESTERS as $value=>$label)<option value="{{ $value }}" @selected($semester === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="primary-button compact" type="submit">Run automatic sectioning</button>
    </form>
</section>

<section class="section-summary-grid">@forelse($sections as $section)<article class="section-summary-card"><div><span class="role-badge role-student">{{ $section->component->code }}</span><span class="status-badge {{ $section->status }}"><i></i>{{ ucfirst($section->status) }}</span></div><h3>{{ $section->code }}</h3><p>{{ $section->name }}</p><div class="section-facilitator"><span>Facilitator</span><strong>{{ $section->facilitator?->name ?? 'Unassigned' }}</strong></div><div class="occupancy-track"><span style="width:{{ min(100,$section->capacity ? $section->enrollments_count/$section->capacity*100 : 0) }}%"></span></div><small>{{ $section->enrollments_count }} of {{ $section->capacity }} seats</small><a class="table-action section-manage-link" href="{{ route($routePrefix.'.sections.edit', $section) }}">Manage section →</a></article>@empty<article class="section-summary-card empty-summary"><strong>No sections for this selection</strong><span>Create one manually or let automated sectioning generate it when needed.</span></article>@endforelse</section>

<section class="card user-table-card">
<div class="sectioning-toolbar"><div><span class="eyebrow">Active student accounts</span><h3>Component enrollment</h3></div><span class="form-help">Use the automatic sectioning panel above after saving component enrollments.</span></div>
<form method="POST" action="{{ route($routePrefix.'.sectioning.enroll') }}" id="enrollment-form">@csrf<input type="hidden" name="academic_year" value="{{ $academicYear }}"><input type="hidden" name="semester" value="{{ $semester }}"><div class="enrollment-action-bar"><label class="field-group"><span>Assign selected students to</span><select name="component_id" required><option value="">Choose a component</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected($componentId === $component->id)>{{ $component->code }}</option>@endforeach</select></label><button class="filter-button" type="submit">Save component enrollment</button></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th class="check-column"><input type="checkbox" aria-label="Select all students" onclick="document.querySelectorAll('.student-check').forEach(box => box.checked = this.checked)"></th><th>Student</th><th>Component</th><th>Section</th><th>Term</th><th class="align-right">Action</th></tr></thead><tbody>
@forelse($students as $student)@php($enrollment=$student->nstpEnrollments->first())<tr><td class="check-column"><input class="student-check" type="checkbox" name="student_ids[]" value="{{ $student->id }}"></td><td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($student->name,0,1)) }}</span><div><strong>{{ $student->name }}</strong><small>{{ $student->email }}</small></div></div></td><td>@if($enrollment)<span class="role-badge role-student">{{ $enrollment->component->code }}</span>@else<span class="muted-cell">Not enrolled</span>@endif</td><td>@if($enrollment?->section)<strong>{{ $enrollment->section->code }}</strong>@elseif($enrollment)<span class="status-badge inactive"><i></i>Awaiting section</span>@else<span class="muted-cell">—</span>@endif</td><td><strong>{{ $academicYear }}</strong><div class="muted-cell">{{ \App\Models\NstpSection::SEMESTERS[$semester] }}</div></td><td class="align-right">@if($enrollment)<button class="link-danger" type="submit" form="remove-enrollment-{{ $enrollment->id }}">Remove</button>@else<span class="muted-cell">—</span>@endif</td></tr>
@empty<tr><td colspan="6"><div class="empty-state"><strong>No active student accounts</strong><span>Create student accounts in User Management before enrollment.</span></div></td></tr>@endforelse
</tbody></table></div></form>
@foreach($students as $student)
    @php($enrollment = $student->nstpEnrollments->first())
    @if($enrollment)
        <form id="remove-enrollment-{{ $enrollment->id }}" method="POST" action="{{ route($routePrefix.'.sectioning.destroy', $enrollment) }}">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endforeach
</section>
@endsection
