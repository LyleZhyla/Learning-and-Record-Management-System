<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\LearningMaterial;
use App\Models\NotificationRule;
use App\Models\NstpEnrollment;
use App\Models\StudentNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentNotificationService
{
    public function learningMaterialPublished(LearningMaterial $material): void
    {
        $rule = NotificationRule::configured(StudentNotification::MATERIAL);
        if ($material->status !== 'published' || ! $rule->is_enabled || empty($rule->channels)) {
            return;
        }

        $material->loadMissing(['component', 'section']);

        $recipients = $this->studentIds($material->component_id, $material->section_id)
            ->merge($this->staffIds($material->component_id, $material->section_id, true))
            ->reject(fn (int $id) => $id === (int) $material->created_by)
            ->unique()
            ->values();

        $this->upsertForUsers($recipients, StudentNotification::MATERIAL, $material->id, $rule, [
            'material_title' => $material->title,
            'component_code' => $material->component?->code,
            'section_code' => $material->section?->code,
        ]);
    }

    public function assessmentPublished(Assessment $assessment): void
    {
        $rule = NotificationRule::configured(StudentNotification::ASSESSMENT);
        if ($assessment->status !== 'published' || ! $rule->is_enabled || empty($rule->channels)) {
            return;
        }

        $assessment->loadMissing('section.component');
        $recipients = $this->studentIds(null, $assessment->section_id)
            ->merge($this->staffIds($assessment->section->component_id, $assessment->section_id, true))
            ->reject(fn (int $id) => $id === (int) $assessment->created_by)
            ->unique()
            ->values();

        $this->upsertForUsers($recipients, StudentNotification::ASSESSMENT, $assessment->id, $rule, [
            'assessment_title' => $assessment->title,
            'component_code' => $assessment->section->component?->code,
            'section_code' => $assessment->section->code,
        ]);
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
        $rule = NotificationRule::configured($type);
        StudentNotification::where('source_id', $record->id)
            ->whereIn('type', [StudentNotification::LATE_ATTENDANCE, StudentNotification::ABSENT_ATTENDANCE])
            ->where('type', '!=', $type)
            ->delete();

        if (! $rule->is_enabled || empty($rule->channels)) {
            StudentNotification::where('source_id', $record->id)->where('type', $type)->delete();

            return;
        }

        $values = [
            'student_name' => $record->student->name,
            'session_title' => $record->attendanceSession->title,
            'status' => strtoupper($record->status),
            'section_code' => $record->attendanceSession->section->code,
        ];
        $this->upsertForUsers(collect([$record->student_id]), $type, $record->id, $rule, $values);

        $section = $record->attendanceSession->section;
        $staffIds = $this->staffIds($section->component_id, $section->id, true)
            ->reject(fn (int $id) => $id === (int) $record->recorded_by)
            ->unique()
            ->values();
        $this->upsertForUsers(
            $staffIds,
            $type,
            $record->id,
            $rule,
            $values,
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

    /** @param array<string, scalar|null> $values */
    private function upsertForUsers(Collection $userIds, string $type, int $sourceId, NotificationRule $rule, array $values): void
    {
        $now = now();
        $rows = $userIds->map(fn (int $userId) => [
            'user_id' => $userId,
            'type' => $type,
            'source_id' => $sourceId,
            'title' => $rule->renderedTitle($values),
            'body' => $rule->renderedBody($values),
            'available_at' => $rule->availableAt($now),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            StudentNotification::upsert($rows, ['user_id', 'type', 'source_id'], ['title', 'body', 'available_at', 'read_at', 'updated_at']);
        }
    }
}
