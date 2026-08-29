<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, QrCodeService $qrCode): View
    {
        $student = $request->user();
        if (blank($student->student_qr_token)) {
            $student->save();
        }
        $records = AttendanceRecord::with('attendanceSession.section.component')
            ->where('student_id', $student->id)
            ->latest('checked_in_at')
            ->paginate(15);
        $qrSvg = $qrCode->generateSvg($student->studentQrPayload());

        return view('student.attendance.index', compact('records', 'qrSvg'));
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
}
