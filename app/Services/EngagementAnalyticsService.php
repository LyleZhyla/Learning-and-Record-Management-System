<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\LearningMaterial;
use App\Models\NstpEnrollment;
use App\Models\ProjectTask;
use Illuminate\Support\Collection;

class EngagementAnalyticsService
{
    public const WEIGHTS = [
        'attendance' => 30,
        'login' => 15,
        'materials' => 15,
        'participation' => 20,
        'submissions' => 20,
    ];

    /** @param Collection<int, NstpEnrollment> $enrollments */
    public function analyze(Collection $enrollments): Collection
    {
        if ($enrollments->isEmpty()) {
            return collect();
        }

        $studentIds = $enrollments->pluck('student_id')->unique()->values();
        $sectionIds = $enrollments->pluck('section_id')->filter()->unique()->values();
        $componentIds = $enrollments->pluck('component_id')->filter()->unique()->values();

        $sessions = AttendanceSession::query()
            ->whereIn('section_id', $sectionIds)
            ->where(fn ($query) => $query->where('status', 'closed')->orWhere('ends_at', '<=', now()))
            ->get()->groupBy('section_id');
        $sessionIds = $sessions->flatten()->pluck('id');
        $attendance = AttendanceRecord::query()
            ->whereIn('attendance_session_id', $sessionIds)
            ->whereIn('student_id', $studentIds)
            ->get()->keyBy(fn (AttendanceRecord $record) => $record->attendance_session_id.':'.$record->student_id);

        $materials = LearningMaterial::query()
            ->whereIn('component_id', $componentIds)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->get();

        $assessments = Assessment::query()
            ->whereIn('section_id', $sectionIds)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->get()->groupBy('section_id');
        $submissions = AssessmentSubmission::query()
            ->whereIn('assessment_id', $assessments->flatten()->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('submitted_at')
            ->get()->keyBy(fn (AssessmentSubmission $submission) => $submission->assessment_id.':'.$submission->student_id);

        $tasks = ProjectTask::query()
            ->with('project')
            ->whereIn('assigned_to', $studentIds)
            ->where('status', '!=', 'cancelled')
            ->get()->groupBy('assigned_to');

        $activityLogs = AuditLog::query()
            ->whereIn('user_id', $studentIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->where(fn ($query) => $query->where('action', 'login')->orWhereIn('route_name', ['student.materials.index', 'student.materials.download']))
            ->get()->groupBy('user_id');

        return $enrollments->map(function (NstpEnrollment $enrollment) use ($sessions, $attendance, $materials, $assessments, $submissions, $tasks, $activityLogs): array {
            $student = $enrollment->student;
            $sectionSessions = $sessions->get($enrollment->section_id, collect());
            $presentValue = $sectionSessions->sum(function (AttendanceSession $session) use ($attendance, $student): float {
                return match ($attendance->get($session->id.':'.$student->id)?->status) {
                    'present' => 1,
                    'late' => .8,
                    default => 0,
                };
            });
            $attendanceFactor = $this->factor(
                'Attendance',
                $sectionSessions->isNotEmpty() ? ($presentValue / $sectionSessions->count()) * 100 : null,
                self::WEIGHTS['attendance'],
                $sectionSessions->isEmpty() ? 'No completed sessions yet' : number_format($presentValue, 1).' weighted attendances / '.$sectionSessions->count().' sessions'
            );

            $logs = $activityLogs->get($student->id, collect());
            $loginCount = $logs->where('action', 'login')->count();
            $loginScore = min(100, ($loginCount / 8) * 100);
            if ($student->last_login_at?->gte(now()->subDays(30)) && $loginScore === 0.0) {
                $loginScore = 12.5;
            }
            $loginFactor = $this->factor('Login activity', $loginScore, self::WEIGHTS['login'], $loginCount.' sign-ins in the last 30 days');

            $availableMaterials = $materials->filter(fn (LearningMaterial $material) => $material->component_id === $enrollment->component_id
                && ($material->section_id === null || $material->section_id === $enrollment->section_id)
            );
            $downloadedIds = $logs->where('route_name', 'student.materials.download')
                ->map(fn (AuditLog $log) => data_get($log->metadata, 'route_parameters.material.id'))
                ->filter()->unique();
            $viewedMaterialCount = min($availableMaterials->count(), $downloadedIds->count() + ($logs->where('route_name', 'student.materials.index')->isNotEmpty() ? 1 : 0));
            $materialFactor = $this->factor(
                'Learning materials',
                $availableMaterials->isNotEmpty() ? ($viewedMaterialCount / $availableMaterials->count()) * 100 : null,
                self::WEIGHTS['materials'],
                $availableMaterials->isEmpty() ? 'No published materials yet' : $viewedMaterialCount.' / '.$availableMaterials->count().' material interactions'
            );

            $studentTasks = $tasks->get($student->id, collect())->filter(fn (ProjectTask $task) => $task->project?->component_id === $enrollment->component_id
                && ($task->project?->section_id === null || $task->project?->section_id === $enrollment->section_id)
            );
            $taskScore = $studentTasks->isEmpty() ? null : $studentTasks->avg(fn (ProjectTask $task) => in_array($task->status, ['submitted', 'completed'], true) ? 100 : (int) $task->progress_percentage);
            $taskFactor = $this->factor(
                'Participation',
                $taskScore,
                self::WEIGHTS['participation'],
                $studentTasks->isEmpty() ? 'No assigned project tasks yet' : $studentTasks->whereIn('status', ['submitted', 'completed'])->count().' / '.$studentTasks->count().' tasks submitted or completed'
            );

            $sectionAssessments = $assessments->get($enrollment->section_id, collect());
            $submittedCount = $sectionAssessments->filter(fn (Assessment $assessment) => $submissions->has($assessment->id.':'.$student->id))->count();
            $submissionFactor = $this->factor(
                'Assessment submissions',
                $sectionAssessments->isNotEmpty() ? ($submittedCount / $sectionAssessments->count()) * 100 : null,
                self::WEIGHTS['submissions'],
                $sectionAssessments->isEmpty() ? 'No published assessments yet' : $submittedCount.' / '.$sectionAssessments->count().' assessments submitted'
            );

            $factors = collect([$attendanceFactor, $loginFactor, $materialFactor, $taskFactor, $submissionFactor]);
            $availableWeight = $factors->where('available', true)->sum('weight');
            $score = $availableWeight > 0
                ? round($factors->where('available', true)->sum(fn (array $factor) => $factor['score'] * $factor['weight']) / $availableWeight, 1)
                : 0;
            [$status, $statusLabel] = match (true) {
                $score >= 75 => ['engaged', 'Engaged'],
                $score >= 50 => ['monitor', 'Monitor'],
                default => ['at_risk', 'At risk'],
            };

            return compact('enrollment', 'student', 'score', 'status', 'statusLabel', 'factors', 'availableWeight') + [
                'coverage' => $factors->where('available', true)->count(),
                'lastActivityAt' => $logs->max('created_at') ?? $student->last_login_at,
            ];
        })->sortBy(fn (array $row) => $row['student']->name)->values();
    }

    private function factor(string $label, ?float $score, int $weight, string $detail): array
    {
        return [
            'label' => $label,
            'score' => $score === null ? null : round(max(0, min(100, $score)), 1),
            'weight' => $weight,
            'available' => $score !== null,
            'detail' => $detail,
        ];
    }
}
