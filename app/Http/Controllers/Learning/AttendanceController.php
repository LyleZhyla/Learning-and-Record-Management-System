<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $sectionId = $request->integer('section') ?: null;
        $sections = $this->access->manageableSections($request->user())->with('component')->orderBy('code')->get();
        $sessions = AttendanceSession::with(['section.component', 'creator'])
            ->withCount([
                'records',
                'records as present_count' => fn ($query) => $query->where('status', 'present'),
                'records as late_count' => fn ($query) => $query->where('status', 'late'),
                'records as absent_count' => fn ($query) => $query->where('status', 'absent'),
            ])
            ->whereIn('section_id', $sections->pluck('id'))
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->latest('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('learning.attendance.index', $this->context($request) + compact('sections', 'sessions', 'sectionId'));
    }

    public function create(Request $request): View
    {
        return view('learning.attendance.create', $this->context($request) + [
            'sections' => $this->access->manageableSections($request->user())->with('component')->where('status', 'active')->orderBy('code')->get(),
            'selectedSection' => $request->integer('section') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'integer', 'exists:nstp_sections,id'],
            'title' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'late_after' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);
        $section = NstpSection::findOrFail($validated['section_id']);
        $this->access->ensureCanManageSection($request->user(), $section);

        if (($validated['late_after'] ?? null) && Carbon::parse($validated['late_after'])->gt(Carbon::parse($validated['ends_at']))) {
            throw ValidationException::withMessages(['late_after' => 'The late threshold must be before the session end time.']);
        }

        $token = Str::random(48);
        $session = AttendanceSession::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'token' => $token,
            'qr_payload' => '',
            'qr_svg' => '',
            'status' => 'open',
        ]);

        $message = $request->user()->isSuperAdmin()
            ? 'Attendance session created. An authorized NSTP Administrator, facilitator, or coordinator can scan student QR codes.'
            : 'Attendance session created. You can now open the camera scanner for enrolled students’ QR codes.';

        return redirect()->route($this->access->routePrefix($request->user()).'.attendance.show', $session)
            ->with('status', $message);
    }

    public function show(Request $request, AttendanceSession $attendance): View
    {
        $attendance->load(['section.component', 'creator', 'records.student']);
        if ($request->user()->isCoordinator()) {
            $this->access->ensureCanScanSection($request->user(), $attendance->section);
        } else {
            $this->access->ensureCanManageSection($request->user(), $attendance->section);
        }
        $enrolledStudents = NstpEnrollment::with('student')
            ->where('section_id', $attendance->section_id)
            ->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->name);

        return view('learning.attendance.show', $this->context($request) + [
            'attendance' => $attendance,
            'enrolledStudents' => $enrolledStudents,
            'canScan' => $request->user()->isFacilitator()
                || $request->user()->isNstpAdmin()
                || $request->user()->isCoordinator(),
            'canManage' => ! $request->user()->isCoordinator(),
        ]);
    }

    public function scan(Request $request, AttendanceSession $attendance): JsonResponse
    {
        $attendance->loadMissing('section');
        $this->access->ensureCanScanSection($request->user(), $attendance->section);
        $validated = $request->validate(['qr_code' => ['required', 'string', 'max:200']]);

        if ($attendance->status !== 'open') {
            throw ValidationException::withMessages(['qr_code' => 'This attendance session is already closed.']);
        }
        if (now()->lt($attendance->starts_at)) {
            throw ValidationException::withMessages(['qr_code' => 'This attendance session has not started yet.']);
        }
        if (now()->gt($attendance->ends_at)) {
            throw ValidationException::withMessages(['qr_code' => 'The attendance session has ended.']);
        }

        $rawCode = trim($validated['qr_code']);
        $token = Str::startsWith($rawCode, 'SNAPIE:STUDENT:')
            ? Str::after($rawCode, 'SNAPIE:STUDENT:')
            : $rawCode;

        $student = User::where('role', 'student')
            ->where('status', 'active')
            ->where('student_qr_token', $token)
            ->first();

        if (! $student) {
            throw ValidationException::withMessages(['qr_code' => 'Student QR code is invalid or inactive.']);
        }

        $isEnrolled = NstpEnrollment::where('section_id', $attendance->section_id)
            ->where('student_id', $student->id)
            ->where('status', 'enrolled')
            ->exists();

        if (! $isEnrolled) {
            throw ValidationException::withMessages(['qr_code' => 'This student is not enrolled in the session’s section.']);
        }

        $status = $attendance->late_after && now()->gt($attendance->late_after) ? 'late' : 'present';
        $record = AttendanceRecord::withArchived()->firstOrCreate(
            ['attendance_session_id' => $attendance->id, 'student_id' => $student->id],
            ['status' => $status, 'checked_in_at' => now(), 'source' => 'qr', 'recorded_by' => $request->user()->id],
        );
        $wasArchived = filled($record->archived_at);
        if ($wasArchived) {
            $record->forceFill([
                'status' => $status,
                'checked_in_at' => now(),
                'source' => 'qr',
                'recorded_by' => $request->user()->id,
                'archived_at' => null,
                'archived_by' => null,
            ])->save();
        }

        return response()->json([
            'message' => ($record->wasRecentlyCreated || $wasArchived)
                ? "{$student->name} was marked {$record->status}."
                : "{$student->name} is already recorded as {$record->status}.",
            'student' => $student->name,
            'status' => $record->status,
            'recorded' => $record->wasRecentlyCreated || $wasArchived,
        ]);
    }

    public function mark(Request $request, AttendanceSession $attendance): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $attendance->section);
        $validated = $request->validate([
            'student_id' => ['required', 'integer', Rule::exists('nstp_enrollments', 'student_id')->where('section_id', $attendance->section_id)],
            'status' => ['required', Rule::in(['present', 'late', 'absent'])],
        ]);

        AttendanceRecord::withArchived()->updateOrCreate(
            ['attendance_session_id' => $attendance->id, 'student_id' => $validated['student_id']],
            [
                'status' => $validated['status'],
                'checked_in_at' => in_array($validated['status'], ['present', 'late']) ? now() : null,
                'source' => 'manual',
                'recorded_by' => $request->user()->id,
                'archived_at' => null,
                'archived_by' => null,
            ],
        );

        return back()->with('status', 'Attendance record updated successfully.');
    }

    public function close(Request $request, AttendanceSession $attendance): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $attendance->section);

        DB::transaction(function () use ($attendance, $request): void {
            $studentIds = NstpEnrollment::where('section_id', $attendance->section_id)->pluck('student_id');
            foreach ($studentIds as $studentId) {
                $record = AttendanceRecord::withArchived()->firstOrCreate(
                    ['attendance_session_id' => $attendance->id, 'student_id' => $studentId],
                    ['status' => 'absent', 'source' => 'system', 'recorded_by' => $request->user()->id],
                );
                if ($record->archived_at) {
                    $record->forceFill(['archived_at' => null, 'archived_by' => null])->save();
                }
            }
            $attendance->update(['status' => 'closed']);
        });

        return back()->with('status', 'Attendance session closed. Missing students were marked absent automatically.');
    }

    private function context(Request $request): array
    {
        return [
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->access->routePrefix($request->user()),
        ];
    }
}
