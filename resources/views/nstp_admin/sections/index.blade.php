@extends('layouts.nstp-admin')

@section('title', 'NSTP Sections')
@section('page-title', 'NSTP Sections')

@section('content')
    <section class="page-actions"><div><span class="eyebrow">Section management</span><h2>Manage NSTP sections</h2><p>Organize sections by component and term, set capacity, and assign facilitators.</p></div><a class="primary-button compact" href="{{ route('nstp_admin.sections.create', request()->only('component')) }}">+ Create section</a></section>
    <section class="card user-table-card">
        <form class="filter-bar" method="GET" action="{{ route('nstp_admin.sections.index') }}">
            <select name="component" aria-label="Filter by component"><option value="">All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected((int)($filters['component'] ?? 0) === $component->id)>{{ $component->code }}</option>@endforeach</select>
            <input name="academic_year" value="{{ $filters['academic_year'] ?? '' }}" placeholder="Academic year, e.g. 2026-2027">
            <select name="semester" aria-label="Filter by semester"><option value="">All semesters</option>@foreach(\App\Models\NstpSection::SEMESTERS as $value=>$label)<option value="{{ $value }}" @selected(($filters['semester'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select>
            <button class="filter-button" type="submit">Apply filters</button>@if(request()->hasAny(['component','academic_year','semester','status']))<a class="clear-filter" href="{{ route('nstp_admin.sections.index') }}">Clear</a>@endif
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Section</th><th>Component</th><th>Term</th><th>Facilitator</th><th>Enrollment</th><th>Status</th><th class="align-right">Action</th></tr></thead><tbody>
            @forelse($sections as $section)<tr><td><div class="user-cell"><span class="table-avatar">{{ substr($section->component->code,0,1) }}</span><div><strong>{{ $section->code }}</strong><small>{{ $section->name }}</small></div></div></td><td><span class="role-badge role-student">{{ $section->component->code }}</span></td><td><strong>{{ $section->academic_year }}</strong><div class="muted-cell">{{ $section->semesterLabel() }}</div></td><td>{{ $section->facilitator?->name ?? 'Unassigned' }}</td><td><span class="capacity-value {{ $section->enrollments_count >= $section->capacity ? 'full' : '' }}">{{ $section->enrollments_count }} / {{ $section->capacity }}</span></td><td><span class="status-badge {{ $section->status }}"><i></i>{{ ucfirst($section->status) }}</span></td><td class="align-right"><a class="table-action" href="{{ route('nstp_admin.sections.edit',$section) }}">Manage →</a></td></tr>
            @empty<tr><td colspan="7"><div class="empty-state"><strong>No sections found</strong><span>Create a section manually or run automated sectioning.</span></div></td></tr>@endforelse
        </tbody></table></div>
        @if($sections->hasPages())<div class="pagination-row"><span>Showing {{ $sections->firstItem() }}–{{ $sections->lastItem() }} of {{ $sections->total() }}</span><div>@if($sections->onFirstPage())<span class="page-button disabled">Previous</span>@else<a class="page-button" href="{{ $sections->previousPageUrl() }}">Previous</a>@endif<span class="page-current">Page {{ $sections->currentPage() }} of {{ $sections->lastPage() }}</span>@if($sections->hasMorePages())<a class="page-button" href="{{ $sections->nextPageUrl() }}">Next</a>@else<span class="page-button disabled">Next</span>@endif</div></div>@endif
    </section>
@endsection
