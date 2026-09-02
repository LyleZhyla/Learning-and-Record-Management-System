<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\NstpEnrollment;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, QrCodeService $qrCode): View
    {
        $student = $request->user()->load('studentProfile');
        if (blank($student->student_qr_token)) {
            $student->save();
        }
        $records = AttendanceRecord::with('attendanceSession.section.component')
            ->where('student_id', $student->id)
            ->latest('checked_in_at')
            ->paginate(15);
        $qrSvg = $qrCode->generateSvg($student->studentQrPayload());

        return view('student.attendance.index', [
            'records' => $records,
            ...$this->studentIdData($student, $qrSvg),
        ]);
    }

    public function qr(Request $request, QrCodeService $qrCode)
    {
        $student = $request->user();
        if (blank($student->student_qr_token)) {
            $student->save();
        }

        return response($qrCode->generateSvg($student->studentQrPayload()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.str($student->name)->slug().'-snapie-qr.svg"',
        ]);
    }

    public function studentId(Request $request, QrCodeService $qrCode): View
    {
        $student = $request->user()->load('studentProfile');
        if (blank($student->student_qr_token)) {
            $student->save();
        }

        $qrSvg = $qrCode->generateSvg($student->studentQrPayload());

        return view('student.id-card', $this->studentIdData($student, $qrSvg));
    }

    private function studentIdData(User $student, string $qrSvg): array
    {
        $details = $student->studentProfile
            ?? StudentRegistration::where('email', $student->email)->latest()->first();
        $enrollment = NstpEnrollment::with('component')
            ->where('student_id', $student->id)
            ->where('status', 'enrolled')
            ->latest('id')
            ->first();
        $middleInitial = filled($details?->middle_name)
            ? Str::upper(Str::substr(trim($details->middle_name), 0, 1)).'.'
            : null;
        $displayName = collect([
            $details?->first_name,
            $middleInitial,
            $details?->last_name,
            $details?->extension_name,
        ])->filter()->implode(' ') ?: $student->name;

        return compact('student', 'details', 'enrollment', 'displayName', 'qrSvg');
    }
}
