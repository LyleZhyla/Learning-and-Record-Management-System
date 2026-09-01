@extends('layouts.student')
@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
<section class="student-qr-identity">
    <div class="student-qr-copy">
        <span class="eyebrow">Your permanent attendance QR</span>
        <h2>Present this code to your facilitator or coordinator.</h2>
        <p>This QR is unique to your SNAPIE account. Keep it private and use the same code for every attendance session.</p>
        <div class="student-qr-actions">
            <a class="primary-button compact" href="{{ route('student.attendance.qr') }}">Download QR</a>
            <button class="secondary-outline-button" type="button" onclick="window.print()">Print QR</button>
        </div>
    </div>
    <div class="student-personal-qr" aria-label="Your unique student attendance QR code">{!! $qrSvg !!}<small>{{ auth()->user()->name }}</small></div>
</section>

<div class="page-actions attendance-history-heading"><div><h2>Your attendance history</h2><p>Records appear after an authorized facilitator or coordinator scans your QR.</p></div></div>
<section class="card user-table-card">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Session</th><th>Section</th><th>Date</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Recorded through</th></tr></thead><tbody>
        @forelse($records as $record)
            <tr><td><strong>{{ $record->attendanceSession->title }}</strong></td><td>{{ $record->attendanceSession->section->code }} · {{ $record->attendanceSession->section->component->code }}</td><td>{{ $record->attendanceSession->starts_at->format('M d, Y') }}</td><td><span class="status-badge {{ in_array($record->status,['present','late'])?'active':'inactive' }}"><i></i>{{ ucfirst($record->status) }}</span></td><td>{{ $record->checked_in_at?->format('g:i A') ?? '—' }}</td><td>{{ $record->checked_out_at?->format('g:i A') ?? '—' }}</td><td>{{ $record->source === 'qr' ? 'Staff QR scan' : ucfirst($record->source) }}</td></tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><strong>No attendance records yet</strong><span>Show your personal QR to your facilitator or coordinator during an active session.</span></div></td></tr>
        @endforelse
    </tbody></table></div>
    @if($records->hasPages())<div class="pagination-row"><span>Showing {{ $records->count() }} of {{ $records->total() }}</span>{{ $records->links() }}</div>@endif
</section>
@endsection
