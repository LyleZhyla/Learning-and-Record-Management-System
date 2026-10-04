<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;

class ProgressMonitoringService
{
    public function __construct(private GradeService $grades) {}

    public function summary(User $student, int $sectionId): array
    {
        $grade = $this->grades->summary($student, $sectionId);
        $sessionIds = AttendanceSession::query()
            ->where('section_id', $sectionId)
            ->where(fn ($query) => $query->where('status', 'closed')->orWhere('ends_at', '<=', now()))
            ->pluck('id');
        $records = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->whereIn('attendance_session_id', $sessionIds)
            ->get();
        $attended = $records->whereIn('status', ['present', 'late'])->count();
        $absent = $records->where('status', 'absent')->count();
        $attendanceRate = $sessionIds->isEmpty() ? null : round(($attended / $sessionIds->count()) * 100, 2);

        $status = $this->overallStatus($grade, $attendanceRate);
        $attentionItems = collect([
            $grade['missing_count'] > 0 ? $grade['missing_count'].' overdue requirement(s) without a submission' : null,
            $grade['awaiting_grading_count'] > 0 ? $grade['awaiting_grading_count'].' submitted requirement(s) awaiting grading' : null,
            $attendanceRate !== null && $attendanceRate < 75 ? 'Attendance is below the 75% monitoring threshold' : null,
            $grade['current_percentage'] !== null && $grade['current_percentage'] < (float) $grade['settings']->passing_percentage
                ? 'Current graded-item standing is below the passing percentage'
                : null,
        ])->filter()->values();

        return $grade + [
            'attendance_sessions' => $sessionIds->count(),
            'attended_sessions' => $attended,
            'absent_sessions' => $absent,
            'attendance_rate' => $attendanceRate,
            'overall_status' => $status['key'],
            'overall_label' => $status['label'],
            'attention_items' => $attentionItems,
        ];
    }

    /** @param array<string, mixed> $grade */
    private function overallStatus(array $grade, ?float $attendanceRate): array
    {
        if ($grade['total_count'] === 0 && $attendanceRate === null) {
            return ['key' => 'not_started', 'label' => 'Not started'];
        }

        $attendanceRisk = $attendanceRate !== null && $attendanceRate < 75;
        $gradeRisk = $grade['current_percentage'] !== null
            && $grade['current_percentage'] < (float) $grade['settings']->passing_percentage;
        $hasMissing = $grade['missing_count'] > 0;

        if ($grade['graded_count'] === $grade['total_count'] && $grade['total_count'] > 0) {
            if (($grade['percentage'] ?? 0) >= (float) $grade['settings']->passing_percentage && ! $attendanceRisk) {
                return ['key' => 'completed', 'label' => 'Requirements completed'];
            }

            return ['key' => 'needs_improvement', 'label' => 'Needs improvement'];
        }
        if ($attendanceRisk || $gradeRisk || $hasMissing) {
            return ['key' => 'at_risk', 'label' => 'Needs attention'];
        }
        if ($grade['graded_count'] > 0 || $grade['submitted_count'] > 0 || $attendanceRate !== null) {
            return ['key' => 'on_track', 'label' => 'On track'];
        }

        return ['key' => 'not_started', 'label' => 'Not started'];
    }
}
