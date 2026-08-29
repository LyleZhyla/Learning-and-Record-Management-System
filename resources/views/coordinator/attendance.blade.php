@extends('layouts.coordinator')
@section('title', 'Attendance')
@section('page-title', 'Attendance Monitoring')

@section('content')
<div class="page-actions">
    <div><h2>Student QR attendance</h2><p>Monitor all attendance sessions and open an active session to scan students’ permanent QR codes.</p></div>
</div>
<section class="card report-filter-card">
    <form method="GET" class="report-filter-grid">
        <label class="field-group"><span>Component</span><select name="component_id"><option value="">All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected(($filters['component_id']??'')==$component->id)>{{ $component->code }}</option>@endforeach</select></label>
        <label class="field-group"><span>Section</span><select name="section_id"><option value="">All sections</option>@foreach($allSections as $section)<option value="{{ $section->id }}" @selected(($filters['section_id']??'')==$section->id)>{{ $section->code }} · {{ $section->component->code }}</option>@endforeach</select></label>
        <label class="field-group"><span>Date from</span><input type="date" name="date_from" value="{{ $filters['date_from']??'' }}"></label>
        <label class="field-group"><span>Date to</span><input type="date" name="date_to" value="{{ $filters['date_to']??'' }}"></label>
        <div class="report-filter-actions"><button class="filter-button">Apply filters</button><a class="clear-filter" href="{{ route('coordinator.attendance.index') }}">Clear</a></div>
    </form>
</section>
<section class="card user-table-card">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Session</th><th>Section</th><th>Schedule</th><th>Created by</th><th>Present</th><th>Late</th><th>Absent</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($sessions as $session)
            <tr><td><strong>{{ $session->title }}</strong></td><td>{{ $session->section->code }} · {{ $session->section->component->code }}</td><td>{{ $session->starts_at->format('M d, Y h:i A') }}<br><small class="muted-cell">to {{ $session->ends_at->format('h:i A') }}</small></td><td>{{ $session->creator->name }}</td><td>{{ $session->present_count }}</td><td>{{ $session->late_count }}</td><td>{{ $session->absent_count }}</td><td><span class="status-badge {{ $session->status==='open'?'active':'inactive' }}"><i></i>{{ ucfirst($session->status) }}</span></td><td><a class="table-action" href="{{ route('coordinator.attendance.show', $session) }}">{{ $session->status === 'open' ? 'Open scanner' : 'View records' }}</a></td></tr>
        @empty
            <tr><td colspan="9"><div class="empty-state"><strong>No attendance sessions found</strong><span>Sessions created by facilitators will appear here.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination-row"><span>Showing {{ $sessions->count() }} of {{ $sessions->total() }}</span>{{ $sessions->links() }}</div>
</section>
@endsection
