<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        [$academicYear, $semester] = $this->currentTerm();
        $components = NstpComponent::query()
            ->whereIn('code', ['CWTS', 'LTS', 'ROTC'])
            ->get()
            ->sortBy(fn (NstpComponent $component): int => array_search($component->code, ['CWTS', 'LTS', 'ROTC'], true))
            ->values();
        $currentEnrollments = NstpEnrollment::query()
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->whereIn('status', ['enrolled', 'pending_approval'])
            ->whereHas('student', fn ($student) => $student
                ->where('role', 'student')
                ->where('status', 'active'))
            ->get(['id', 'student_id', 'component_id', 'section_id', 'status']);
        $attendanceRecords = AttendanceRecord::query()
            ->select(['id', 'attendance_session_id', 'status'])
            ->with('attendanceSession.section:id,component_id,academic_year,semester')
            ->whereHas('attendanceSession.section', fn ($section) => $section
                ->where('academic_year', $academicYear)
                ->where('semester', $semester))
            ->get();
        $attendanceByComponent = $attendanceRecords
            ->filter(fn (AttendanceRecord $record): bool => $record->attendanceSession?->section?->component_id !== null)
            ->groupBy(fn (AttendanceRecord $record): int => $record->attendanceSession->section->component_id);
        $componentAnalytics = $components->map(function (NstpComponent $component) use ($currentEnrollments, $attendanceByComponent, $academicYear, $semester): array {
            $enrollments = $currentEnrollments->where('component_id', $component->id);
            $records = $attendanceByComponent->get($component->id, collect());
            $present = $records->where('status', 'present')->count();
            $late = $records->where('status', 'late')->count();
            $absent = $records->where('status', 'absent')->count();
            $recordCount = $records->count();

            return [
                'id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'enrolled' => $enrollments->count(),
                'sectioned' => $enrollments->whereNotNull('section_id')->count(),
                'sections' => NstpSection::query()
                    ->where('component_id', $component->id)
                    ->where('academic_year', $academicYear)
                    ->where('semester', $semester)
                    ->where('status', 'active')
                    ->count(),
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'attendance_records' => $recordCount,
                'attendance_rate' => $recordCount > 0 ? round((($present + $late) / $recordCount) * 100, 1) : 0,
                'present_percentage' => $recordCount > 0 ? ($present / $recordCount) * 100 : 0,
                'late_percentage' => $recordCount > 0 ? ($late / $recordCount) * 100 : 0,
                'absent_percentage' => $recordCount > 0 ? ($absent / $recordCount) * 100 : 0,
            ];
        });
        $enrolledStudentCount = $currentEnrollments->unique('student_id')->count();
        $sectionedStudentCount = $currentEnrollments->whereNotNull('section_id')->unique('student_id')->count();
        $unsectionedStudentCount = $currentEnrollments->whereNull('section_id')->unique('student_id')->count();
        $attendanceRecordCount = $attendanceRecords->count();
        $attendedRecordCount = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        return view('nstp_admin.dashboard', [
            'components' => $components,
            'studentCount' => User::where('role', 'student')->where('status', 'active')->count(),
            'enrolledStudentCount' => $enrolledStudentCount,
            'sectionedStudentCount' => $sectionedStudentCount,
            'unsectionedStudentCount' => $unsectionedStudentCount,
            'sectioningRate' => $enrolledStudentCount > 0 ? round(($sectionedStudentCount / $enrolledStudentCount) * 100, 1) : 0,
            'attendanceRate' => $attendanceRecordCount > 0 ? round(($attendedRecordCount / $attendanceRecordCount) * 100, 1) : 0,
            'attendanceRecordCount' => $attendanceRecordCount,
            'componentAnalytics' => $componentAnalytics,
            'largestEnrollment' => max(1, (int) $componentAnalytics->max('enrolled')),
            'unassignedStudentCount' => User::query()
                ->where('role', 'student')
                ->where('status', 'active')
                ->whereDoesntHave('nstpEnrollments', fn ($query) => $query
                    ->where('academic_year', $academicYear)
                    ->where('semester', $semester))
                ->count(),
            'academicTerm' => (NstpSection::SEMESTERS[$semester] ?? str($semester)->headline()).' '.$academicYear,
            'recentAccounts' => User::where('role', 'student')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
