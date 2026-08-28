<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\PortalAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $records = AttendanceRecord::with('attendanceSession.section.component')
            ->where('student_id', $request->user()->id)
            ->latest('checked_in_at')
            ->paginate(15);

        return view('student.attendance.index', compact('records'));
    }

    public function checkIn(Request $request, string $token): View
    {
        $session = AttendanceSession::with('section.component')->where('token', $token)->firstOrFail();
        $enrollment = $this->access->currentEnrollment($request->user());
        $message = null;
        $record = null;

        if (! $enrollment || $enrollment->section_id !== $session->section_id) {
            $message = 'This QR code is not assigned to your NSTP section.';
        } elseif ($session->status !== 'open') {
            $message = 'This attendance session is already closed.';
        } elseif (now()->lt($session->starts_at)) {
            $message = 'Check-in has not started yet.';
        } elseif (now()->gt($session->ends_at)) {
            $message = 'The check-in period has ended.';
        } else {
            $status = $session->late_after && now()->gt($session->late_after) ? 'late' : 'present';
            $record = AttendanceRecord::updateOrCreate(
                ['attendance_session_id' => $session->id, 'student_id' => $request->user()->id],
                ['status' => $status, 'checked_in_at' => now(), 'source' => 'qr', 'recorded_by' => null],
            );
        }

        return view('student.attendance.checkin', compact('session', 'record', 'message'));
    }
}
