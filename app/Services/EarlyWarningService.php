<?php

namespace App\Services;

use App\Models\InterventionActivity;
use App\Models\NotificationRule;
use App\Models\NstpSection;
use App\Models\RiskThreshold;
use App\Models\StudentIntervention;
use App\Models\StudentNotification;
use App\Models\StudentRiskSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EarlyWarningService
{
    public function thresholdFor(int $componentId): RiskThreshold
    {
        return RiskThreshold::firstOrCreate(['component_id' => $componentId], [
            'minimum_attendance_percentage' => 75,
            'minimum_grade_percentage' => 75,
            'maximum_missing_requirements' => 0,
            'follow_up_days' => 7,
            'escalation_days' => 14,
            'minimum_improvement_points' => 5,
        ]);
    }

    /** @param array<string, mixed> $summary */
    public function synchronize(User $student, NstpSection $section, array $summary): ?StudentRiskSnapshot
    {
        $threshold = $this->thresholdFor($section->component_id);
        [$score, $reasons] = $this->score($summary, $threshold);
        $status = $score > 0 ? 'at_risk' : ($summary['overall_status'] ?? 'on_track');
        $latest = StudentRiskSnapshot::where('student_id', $student->id)->where('section_id', $section->id)->latest('calculated_at')->first();
        $signature = [$score, $status, $summary['attendance_rate'] ?? null, $summary['current_percentage'] ?? null, $summary['missing_count'] ?? 0, $reasons];
        $previousSignature = $latest ? [$latest->risk_score, $latest->status, $latest->attendance_percentage, $latest->grade_percentage, $latest->missing_requirements, $latest->reasons ?? []] : null;

        if ($previousSignature === $signature) {
            $this->escalateIfNeeded($student, $section, $latest, $threshold);

            return $latest;
        }

        return DB::transaction(function () use ($student, $section, $summary, $score, $status, $reasons, $threshold): StudentRiskSnapshot {
            $snapshot = StudentRiskSnapshot::create([
                'student_id' => $student->id,
                'section_id' => $section->id,
                'component_id' => $section->component_id,
                'risk_score' => $score,
                'status' => $status,
                'attendance_percentage' => $summary['attendance_rate'] ?? null,
                'grade_percentage' => $summary['current_percentage'] ?? null,
                'missing_requirements' => $summary['missing_count'] ?? 0,
                'reasons' => $reasons,
                'calculated_at' => now(),
            ]);

            if ($score > 0) {
                $intervention = StudentIntervention::where('student_id', $student->id)
                    ->where('section_id', $section->id)->whereIn('status', ['open', 'in_progress', 'escalated'])->latest()->first();
                if (! $intervention) {
                    $intervention = StudentIntervention::create([
                        'student_id' => $student->id,
                        'section_id' => $section->id,
                        'risk_snapshot_id' => $snapshot->id,
                        'assigned_to' => $this->defaultAssignee($section),
                        'status' => 'open',
                        'severity' => $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low'),
                        'referral_type' => ($summary['attendance_rate'] ?? 100) < $threshold->minimum_attendance_percentage ? 'attendance_counseling' : 'academic_support',
                        'notes' => implode('; ', $reasons),
                        'follow_up_date' => now()->addDays($threshold->follow_up_days)->toDateString(),
                    ]);
                    InterventionActivity::create(['intervention_id' => $intervention->id, 'action' => 'automatically_opened', 'details' => 'Risk score '.$score.': '.implode('; ', $reasons)]);
                    $this->notify($intervention, false);
                } else {
                    InterventionActivity::create(['intervention_id' => $intervention->id, 'action' => 'risk_updated', 'details' => 'Risk score updated to '.$score.'.']);
                }
                $this->escalateIfNeeded($student, $section, $snapshot, $threshold, $intervention);
            }

            return $snapshot;
        });
    }

    /** @param array<string, mixed> $summary @return array{int, array<int, string>} */
    private function score(array $summary, RiskThreshold $threshold): array
    {
        $score = 0;
        $reasons = [];
        $attendance = $summary['attendance_rate'] ?? null;
        $grade = $summary['current_percentage'] ?? null;
        $missing = (int) ($summary['missing_count'] ?? 0);
        if ($attendance !== null && $attendance < $threshold->minimum_attendance_percentage) {
            $score += min(40, (int) ceil(($threshold->minimum_attendance_percentage - $attendance) * 2));
            $reasons[] = 'Attendance '.$attendance.'% is below '.$threshold->minimum_attendance_percentage.'%';
        }
        if ($grade !== null && $grade < $threshold->minimum_grade_percentage) {
            $score += min(40, (int) ceil(($threshold->minimum_grade_percentage - $grade) * 2));
            $reasons[] = 'Standing '.$grade.'% is below '.$threshold->minimum_grade_percentage.'%';
        }
        if ($missing > $threshold->maximum_missing_requirements) {
            $score += min(20, ($missing - $threshold->maximum_missing_requirements) * 5);
            $reasons[] = $missing.' missing requirement(s) exceed the allowed '.$threshold->maximum_missing_requirements;
        }

        return [min(100, $score), $reasons];
    }

    private function defaultAssignee(NstpSection $section): ?int
    {
        return $section->facilitator_id ?: User::where('role', 'coordinator')->where('nstp_component_id', $section->component_id)->where('status', 'active')->value('id');
    }

    private function escalateIfNeeded(User $student, NstpSection $section, StudentRiskSnapshot $latest, RiskThreshold $threshold, ?StudentIntervention $intervention = null): void
    {
        $intervention ??= StudentIntervention::where('student_id', $student->id)->where('section_id', $section->id)
            ->whereIn('status', ['open', 'in_progress', 'escalated'])->oldest()->first();
        if (! $intervention || $intervention->status === 'escalated' || $intervention->created_at->gt(now()->subDays($threshold->escalation_days))) {
            return;
        }
        $initialScore = (int) ($intervention->riskSnapshot?->risk_score ?? $latest->risk_score);
        if (($initialScore - $latest->risk_score) >= $threshold->minimum_improvement_points || $latest->risk_score === 0) {
            return;
        }
        $intervention->update(['status' => 'escalated', 'escalation_count' => $intervention->escalation_count + 1, 'escalated_at' => now()]);
        InterventionActivity::create(['intervention_id' => $intervention->id, 'action' => 'automatically_escalated', 'details' => 'No required risk-score improvement after '.$threshold->escalation_days.' days.']);
        $this->notify($intervention, true);
    }

    private function notify(StudentIntervention $intervention, bool $escalated): void
    {
        $rule = NotificationRule::configured(StudentNotification::EARLY_WARNING);
        if (! $rule->is_enabled || empty($rule->channels)) {
            return;
        }
        $intervention->loadMissing(['student', 'section.component']);
        $recipients = collect([$intervention->student_id, $intervention->section->facilitator_id])
            ->merge(User::where('role', 'coordinator')->where('nstp_component_id', $intervention->section->component_id)->where('status', 'active')->pluck('id'))
            ->filter()->unique();
        $now = now();
        $rows = $recipients->map(fn (int $id): array => [
            'institution_id' => $intervention->institution_id,
            'user_id' => $id,
            'type' => StudentNotification::EARLY_WARNING,
            'source_id' => $intervention->id,
            'title' => $escalated ? 'Early-warning case escalated' : 'Early-warning intervention opened',
            'body' => $intervention->student->name.' · '.$intervention->section->name.' · '.str($intervention->severity)->headline(),
            'available_at' => $rule->availableAt($now),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        StudentNotification::upsert($rows, ['user_id', 'type', 'source_id'], ['title', 'body', 'available_at', 'read_at', 'updated_at']);
    }
}
