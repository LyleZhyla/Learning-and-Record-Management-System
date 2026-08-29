@extends('layouts.facilitator')
@section('title', 'My Students') @section('page-title', 'My Students')
@section('content')
<section class="page-actions"><div><span class="eyebrow">Assigned sections only</span><h2>Student directory</h2><p>Only students enrolled in sections assigned to you are shown.</p></div></section>

<section class="card user-table-card">
    <form class="filter-bar" method="GET"><label class="search-field"><span>⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email"></label><select name="section_id"><option value="">All my sections</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(($filters['section_id'] ?? '') == $section->id)>{{ $section->component->code }} · {{ $section->code }}</option>@endforeach</select><button class="filter-button">Apply filters</button>@if(request()->hasAny(['search','section_id']))<a class="clear-filter" href="{{ route('facilitator.students.index') }}">Clear</a>@endif</form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Name and email</th><th>Assigned section</th><th>Enrollment status</th><th class="align-right">Details</th></tr></thead><tbody>
    @forelse($students as $student)
        <tr><td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($student->name,0,1)) }}</span><div><strong>{{ $student->name }}</strong><small>{{ $student->email }}</small></div></div></td><td>@foreach($student->nstpEnrollments as $enrollment)<span class="component-mini-badge">{{ $enrollment->component?->code }} · {{ $enrollment->section?->code ?? 'Unassigned' }}</span>@endforeach</td><td>{{ $student->nstpEnrollments->pluck('status')->map(fn($status) => ucfirst($status))->unique()->join(', ') }}</td><td class="align-right"><a class="table-action" href="{{ route('facilitator.students.show', $student) }}">View →</a></td></tr>
    @empty<tr><td colspan="4"><div class="empty-state"><strong>No assigned students</strong><span>Students will appear after they are enrolled in one of your sections.</span></div></td></tr>@endforelse
    </tbody></table></div>
    @if($students->hasPages())<div class="pagination-row"><span>Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }}</span>{{ $students->links() }}</div>@endif
</section>
@endsection
