@extends($layout)
@section('title', $attendance->title)
@section('page-title', 'Attendance Session')

@section('content')
<div class="back-row"><a href="{{ route($routePrefix.'.attendance.index') }}">← Back to attendance</a></div>

<section class="card scanner-session-header">
    <div>
        <span class="eyebrow">{{ $attendance->section->component->code }} · {{ $attendance->section->code }}</span>
        <h2>{{ $attendance->title }}</h2>
        <p>{{ $attendance->starts_at->format('M d, Y · g:i A') }} – {{ $attendance->ends_at->format('g:i A') }}</p>
    </div>
    <div class="scanner-session-actions">
        <span class="status-badge {{ $attendance->status === 'open' ? 'active' : 'inactive' }}"><i></i>{{ ucfirst($attendance->status) }}</span>
        @if ($canManage && $attendance->status === 'open')
            <form method="POST" action="{{ route($routePrefix.'.attendance.close', $attendance) }}" onsubmit="return confirm('Close this session and mark missing students absent?')">
                @csrf
                @method('PATCH')
                <button class="danger-button">Close session</button>
            </form>
        @endif
    </div>
</section>

<div class="attendance-scanner-layout">
    @if ($canScan)
        <section class="card student-qr-scanner" data-attendance-scanner data-endpoint="{{ route($routePrefix.'.attendance.scan', $attendance) }}" data-mode-endpoint="{{ route($routePrefix.'.attendance.scan-mode', $attendance) }}" data-scan-mode="{{ $attendance->scan_mode }}">
            <div class="card-heading">
                <div>
                    <span class="eyebrow">Student QR scanner</span>
                    <h3>Scan with device camera</h3>
                    <p>Point the camera at the permanent SNAPIE QR shown on the student’s account.</p>
                </div>
            </div>

            @if ($attendance->status === 'open')
                <div class="attendance-scan-mode is-{{ str_replace('_', '-', $attendance->scan_mode) }}" data-scan-mode-panel>
                    <span>Current scan action</span>
                    <strong data-scan-mode-indicator>{{ strtoupper(str_replace('_', ' ', $attendance->scan_mode)) }}</strong>
                    <small data-scan-mode-help>{{ $attendance->scan_mode === 'time_out' ? 'Scans will record departure time. Students must Time In first.' : 'Scans will record arrival time and determine Present or Late.' }}</small>
                    <div class="attendance-scan-mode-buttons" aria-label="Choose attendance scan action">
                        <button type="button" data-scan-mode-option="time_in" @class(['active' => $attendance->scan_mode === 'time_in'])>Time In</button>
                        <button type="button" data-scan-mode-option="time_out" @class(['active' => $attendance->scan_mode === 'time_out'])>Time Out</button>
                    </div>
                </div>
                <div class="scanner-viewport">
                    <video data-scanner-video playsinline muted></video>
                    <div class="scanner-guide" aria-hidden="true"></div>
                    <div class="scanner-placeholder" data-scanner-placeholder>
                        <span>▣</span>
                        <strong>Camera is off</strong>
                        <small>Allow camera access to scan a student QR.</small>
                    </div>
                </div>
                <button class="primary-button scanner-start-button" type="button" data-scanner-start>Open camera</button>
                <p class="scanner-message" data-scanner-message role="status">Ready to scan. Each successful scan is saved automatically.</p>

                <div class="scanner-divider"><span>or enter the QR code manually</span></div>
                <form class="scanner-manual-form" data-scanner-form>
                    <input name="qr_code" data-scanner-input autocomplete="off" placeholder="SNAPIE student QR code" required>
                    <button class="filter-button" type="submit" data-scanner-submit>Record {{ $attendance->scan_mode === 'time_out' ? 'Time Out' : 'Time In' }}</button>
                </form>
            @else
                <div class="scanner-closed-state"><span>✓</span><strong>Session closed</strong><p>Scanning is no longer available for this session.</p></div>
            @endif

        </section>
    @endif

    <section class="card attendance-record-panel {{ $canScan ? '' : 'full-width' }}">
        <div class="card-heading">
            <div><h3>Attendance records</h3><p>Scanned students appear here. Duplicate scans do not create duplicate records.</p></div>
            <span class="pill" data-attendance-record-count="{{ $attendance->records->count() }}">{{ $attendance->records->count() }} recorded</span>
        </div>
        @php($recordColumnCount = 4 + ($canScan ? 0 : 1) + ($canManage ? 1 : 0))
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Student</th><th>Status</th><th>Time In</th><th>Time Out</th>@unless($canScan)<th>Source</th>@endunless @if($canManage)<th>Manual update</th>@endif</tr></thead>
                <tbody>
                    @forelse ($enrolledStudents as $enrollment)
                        @php($record = $attendance->records->firstWhere('student_id', $enrollment->student_id))
                        <tr data-attendance-student="{{ $enrollment->student_id }}" data-attendance-recorded="{{ $record ? 1 : 0 }}">
                            <td><strong>{{ $enrollment->student->name }}</strong><br><small class="muted-cell">{{ $enrollment->student->email }}</small></td>
                            <td><span class="status-badge {{ $record && in_array($record->status, ['present','late']) ? 'active' : 'inactive' }}" data-record-status><i></i>{{ $record ? ucfirst($record->status) : 'Not recorded' }}</span></td>
                            <td data-record-check-in>{{ $record?->checked_in_at?->format('g:i:s A') ?? '—' }}</td>
                            <td data-record-check-out>{{ $record?->checked_out_at?->format('g:i:s A') ?? '—' }}</td>
                            @unless($canScan)<td>{{ $record ? strtoupper($record->source) : '—' }}</td>@endunless
                            @if ($canManage)
                                <td><form class="inline-record-form" method="POST" action="{{ route($routePrefix.'.attendance.mark', $attendance) }}">@csrf<input type="hidden" name="student_id" value="{{ $enrollment->student_id }}"><select name="status"><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option></select><button class="filter-button">Save</button></form></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $recordColumnCount }}"><div class="empty-state"><strong>No enrolled students</strong><span>Assign students to this section before recording attendance.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@if ($canScan && $attendance->status === 'open')
    <script src="{{ asset('js/vendor/jsQR.js') }}?v={{ filemtime(public_path('js/vendor/jsQR.js')) }}"></script>
    <script src="{{ asset('js/attendance-scanner.js') }}?v={{ filemtime(public_path('js/attendance-scanner.js')) }}"></script>
@endif
@endsection
