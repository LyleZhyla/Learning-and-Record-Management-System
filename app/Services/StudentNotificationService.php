<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\LearningMaterial;
use App\Models\NstpEnrollment;
use App\Models\StudentNotification;
use Illuminate\Support\Collection;

class StudentNotificationService
{
    public function learningMaterialPublished(LearningMaterial $material): void
    {
        if ($material->status !== 'published') {
            return;
        }

        $this->upsertForStudents(
            $this->studentIds($material->component_id, $material->section_id),
            StudentNotification::MATERIAL,
            $material->id,
            'New learning material',
            $material->title.' is now available in Learning Materials.',
        );
    }

    public function assessmentPublished(Assessment $assessment): void
    {
        if ($assessment->status !== 'published') {
            return;
        }

        $this->upsertForStudents(
            $this->studentIds(null, $assessment->section_id),
            StudentNotification::ASSESSMENT,
            $assessment->id,
            'New assessment',
            $assessment->title.' is now available in Assessments.',
        );
    }

    public function attendanceRecorded(AttendanceRecord $record): void
    {
        if (! in_array($record->status, ['late', 'absent'], true)) {
            StudentNotification::where('user_id', $record->student_id)
                ->where('source_id', $record->id)
                ->whereIn('type', [StudentNotification::LATE_ATTENDANCE, StudentNotification::ABSENT_ATTENDANCE])
                ->delete();

            return;
        }

        $record->loadMissing('attendanceSession');
        $late = $record->status === 'late';
        $type = $late ? StudentNotification::LATE_ATTENDANCE : StudentNotification::ABSENT_ATTENDANCE;
        StudentNotification::where('user_id', $record->student_id)
            ->where('source_id', $record->id)
            ->whereIn('type', [StudentNotification::LATE_ATTENDANCE, StudentNotification::ABSENT_ATTENDANCE])
            ->where('type', '!=', $type)
            ->delete();

        StudentNotification::firstOrCreate(
            [
                'user_id' => $record->student_id,
                'type' => $type,
                'source_id' => $record->id,
            ],
            [
                'title' => $late ? 'Late attendance recorded' : 'Absent attendance recorded',
                'body' => 'Your attendance for '.$record->attendanceSession->title.' was marked '.strtoupper($record->status).'.',
            ],
        );
    }

    private function studentIds(?int $componentId, ?int $sectionId): Collection
    {
        return NstpEnrollment::query()
            ->where('status', 'enrolled')
            ->when($componentId, fn ($query) => $query->where('component_id', $componentId))
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->whereHas('student', fn ($query) => $query->where('role', 'student')->where('status', 'active'))
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    private function upsertForStudents(Collection $studentIds, string $type, int $sourceId, string $title, string $body): void
    {
        $now = now();
        $rows = $studentIds->map(fn (int $studentId) => [
            'user_id' => $studentId,
            'type' => $type,
            'source_id' => $sourceId,
            'title' => $title,
            'body' => $body,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            StudentNotification::upsert($rows, ['user_id', 'type', 'source_id'], ['title', 'body', 'read_at', 'updated_at']);
        }
    }
}
