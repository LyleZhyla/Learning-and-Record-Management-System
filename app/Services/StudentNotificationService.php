<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\LearningMaterial;
use App\Models\NstpEnrollment;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentNotificationService
{
    public function learningMaterialPublished(LearningMaterial $material): void
    {
        if ($material->status !== 'published') {
            return;
        }

        $recipients = $this->studentIds($material->component_id, $material->section_id)
            ->merge($this->staffIds($material->component_id, $material->section_id, false))
            ->reject(fn (int $id) => $id === (int) $material->created_by)
            ->unique()
            ->values();

        $this->upsertForUsers($recipients, StudentNotification::MATERIAL, $material->id,
            'New learning material', $material->title.' is now available in Learning Materials.');
    }

    public function assessmentPublished(Assessment $assessment): void
    {
        if ($assessment->status !== 'published') {
            return;
        }

        $assessment->loadMissing('section');
        $recipients = $this->studentIds(null, $assessment->section_id)
            ->merge($this->staffIds($assessment->section->component_id, $assessment->section_id, true))
            ->reject(fn (int $id) => $id === (int) $assessment->created_by)
            ->unique()
            ->values();

        $this->upsertForUsers($recipients, StudentNotification::ASSESSMENT, $assessment->id,
            'New assessment', $assessment->title.' is now available in Assessments.');
    }

    public function attendanceRecorded(AttendanceRecord $record): void
    {
        if (! in_array($record->status, ['late', 'absent'], true)) {
            StudentNotification::where('source_id', $record->id)
                ->whereIn('type', [StudentNotification::LATE_ATTENDANCE, StudentNotification::ABSENT_ATTENDANCE])
                ->delete();

            return;
        }

        $record->loadMissing(['attendanceSession.section', 'student']);
        $late = $record->status === 'late';
        $type = $late ? StudentNotification::LATE_ATTENDANCE : StudentNotification::ABSENT_ATTENDANCE;
        StudentNotification::where('source_id', $record->id)
            ->whereIn('type', [StudentNotification::LATE_ATTENDANCE, StudentNotification::ABSENT_ATTENDANCE])
            ->where('type', '!=', $type)
            ->delete();

        StudentNotification::firstOrCreate(
            ['user_id' => $record->student_id, 'type' => $type, 'source_id' => $record->id],
            [
                'title' => $late ? 'Late attendance recorded' : 'Absent attendance recorded',
                'body' => 'Your attendance for '.$record->attendanceSession->title.' was marked '.strtoupper($record->status).'.',
            ],
        );

        $section = $record->attendanceSession->section;
        $staffIds = $this->staffIds($section->component_id, $section->id, true)
            ->reject(fn (int $id) => $id === (int) $record->recorded_by)
            ->unique()
            ->values();
        $this->upsertForUsers(
            $staffIds,
            $type,
            $record->id,
            $late ? 'Late attendance update' : 'Absent attendance update',
            $record->student->name.' was marked '.strtoupper($record->status).' for '.$record->attendanceSession->title.'.',
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

    private function staffIds(int $componentId, ?int $sectionId, bool $includeCoordinators): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->where(function ($query) use ($componentId, $sectionId, $includeCoordinators): void {
                $query->whereIn('role', ['super_admin', 'nstp_admin'])
                    ->orWhere(function ($facilitators) use ($componentId, $sectionId): void {
                        $facilitators->where('role', 'facilitator')
                            ->whereHas('facilitatedSections', fn ($sections) => $sections
                                ->where('component_id', $componentId)
                                ->when($sectionId, fn ($items) => $items->whereKey($sectionId)));
                    });

                if ($includeCoordinators) {
                    $query->orWhere(fn ($coordinators) => $coordinators
                        ->where('role', 'coordinator')
                        ->where('nstp_component_id', $componentId));
                }
            })
            ->pluck('id');
    }

    private function upsertForUsers(Collection $userIds, string $type, int $sourceId, string $title, string $body): void
    {
        $now = now();
        $rows = $userIds->map(fn (int $userId) => [
            'user_id' => $userId,
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
