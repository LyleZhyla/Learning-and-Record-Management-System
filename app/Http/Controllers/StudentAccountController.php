<?php

namespace App\Http\Controllers;

use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentAccountController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(User::STATUS_LABELS))],
        ]);
        $prefix = $this->routePrefix($request);

        $students = User::query()
            ->where('role', 'student')
            ->with(['nstpEnrollments' => fn ($query) => $query
                ->with(['component', 'section'])
                ->latest('academic_year')
                ->latest('semester')])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query
                ->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('student-accounts.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'students' => $students,
            'filters' => $filters,
            'activeCount' => User::where('role', 'student')->where('status', 'active')->count(),
            'inactiveCount' => User::where('role', 'student')->where('status', 'inactive')->count(),
            'availableComponents' => $prefix === 'nstp_admin'
                ? NstpComponent::where('is_active', true)->orderBy('code')->get()
                : collect(),
            'academicYear' => $this->currentAcademicYear(),
            'semesterLabel' => NstpSection::SEMESTERS[$this->currentSemester()],
        ]);
    }

    public function qr(Request $request, User $student, QrCodeService $qrCode): Response
    {
        $this->ensureStudent($student);

        return response($qrCode->generateSvg($student->studentQrPayload()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.Str::slug($student->name).'-attendance-qr.svg"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadQr(Request $request, User $student, QrCodeService $qrCode): Response
    {
        $this->ensureStudent($student);

        return response($qrCode->generateSvg($student->studentQrPayload()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($student->name).'-attendance-qr.svg"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ensureStudent(User $student): void
    {
        abort_unless($student->isStudent(), 404);

        if (blank($student->student_qr_token)) {
            $student->update(['student_qr_token' => Str::random(48)]);
        }
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }

    private function currentAcademicYear(): string
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return $start.'-'.($start + 1);
    }

    private function currentSemester(): string
    {
        return now()->month >= 6 ? 'first' : 'second';
    }
}
