<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\GradingCategory;
use App\Models\GradingSetting;
use App\Models\SystemSetting;
use App\Models\User;

class GradeService
{
    public function summary(User $student, int $sectionId): array
    {
        $this->ensureStructure($sectionId);
        $categories = GradingCategory::with(['assessments' => function ($query) use ($student) {
            $query->where('status', 'published')
                ->with(['submissions' => fn ($submissionQuery) => $submissionQuery->where('student_id', $student->id)]);
        }])->where('section_id', $sectionId)->orderBy('sort_order')->orderBy('id')->get();

        $setting = GradingSetting::firstOrCreate(['section_id' => $sectionId], $this->defaultSettings());
        $earnedPercentage = 0.0;
        $graded = 0;
        $total = 0;
        $rawEarned = 0.0;
        $rawMaximum = 0.0;
        $currentWeighted = 0.0;
        $activeWeight = 0.0;
        $submitted = 0;
        $awaitingGrading = 0;
        $missing = 0;

        $categorySummaries = $categories->map(function (GradingCategory $category) use (&$earnedPercentage, &$graded, &$total, &$rawEarned, &$rawMaximum, &$currentWeighted, &$activeWeight, &$submitted, &$awaitingGrading, &$missing) {
            $maximum = (float) $category->assessments->sum('max_score');
            $earned = 0.0;
            $categoryGraded = 0;
            $gradedMaximum = 0.0;

            foreach ($category->assessments as $assessment) {
                $submission = $assessment->submissions->first();
                if ($submission?->submitted_at) {
                    $submitted++;
                }
                if ($submission?->score !== null) {
                    $earned += (float) $submission->score;
                    $rawEarned += (float) $submission->score;
                    $rawMaximum += (float) $assessment->max_score;
                    $gradedMaximum += (float) $assessment->max_score;
                    $categoryGraded++;
                } elseif ($submission?->submitted_at) {
                    $awaitingGrading++;
                } elseif ($assessment->due_at?->isPast()) {
                    $missing++;
                }
            }

            $weighted = $maximum > 0 ? ($earned / $maximum) * (float) $category->weight : 0.0;
            $currentCategoryWeighted = $gradedMaximum > 0 ? ($earned / $gradedMaximum) * (float) $category->weight : null;
            $earnedPercentage += $weighted;
            if ($currentCategoryWeighted !== null) {
                $currentWeighted += $currentCategoryWeighted;
                $activeWeight += (float) $category->weight;
            }
            $graded += $categoryGraded;
            $total += $category->assessments->count();

            return [
                'category' => $category,
                'earned' => round($earned, 2),
                'maximum' => round($maximum, 2),
                'graded_maximum' => round($gradedMaximum, 2),
                'weighted_score' => round($weighted, 2),
                'current_weighted_score' => $currentCategoryWeighted === null ? null : round($currentCategoryWeighted, 2),
                'graded_count' => $categoryGraded,
                'total_count' => $category->assessments->count(),
                'completion_percentage' => $category->assessments->isEmpty()
                    ? 0.0
                    : round(($categoryGraded / $category->assessments->count()) * 100, 2),
            ];
        });

        $percentage = $graded > 0 ? round($earnedPercentage, 2) : null;
        $currentPercentage = $activeWeight > 0 ? round(($currentWeighted / $activeWeight) * 100, 2) : null;
        $completionPercentage = $total > 0 ? round(($graded / $total) * 100, 2) : 0.0;
        $progressStatus = $this->progressStatus($graded, $total, $percentage, $currentPercentage, (float) $setting->passing_percentage);

        return [
            'assessments' => $categories->flatMap->assessments,
            'categories' => $categorySummaries,
            'grade' => $percentage === null ? null : $this->transmute($percentage, $setting),
            'percentage' => $percentage,
            'current_percentage' => $currentPercentage,
            'current_grade' => $currentPercentage === null ? null : $this->transmute($currentPercentage, $setting),
            'raw_percentage' => $graded > 0 && $rawMaximum > 0 ? round(($rawEarned / $rawMaximum) * 100, 2) : null,
            'graded_count' => $graded,
            'total_count' => $total,
            'pending_count' => max(0, $total - $graded),
            'submitted_count' => $submitted,
            'awaiting_grading_count' => $awaitingGrading,
            'missing_count' => $missing,
            'completion_percentage' => $completionPercentage,
            'progress_status' => $progressStatus['key'],
            'progress_label' => $progressStatus['label'],
            'total_weight' => (float) $categories->sum('weight'),
            'settings' => $setting,
        ];
    }

    public function transmute(float $percentage, GradingSetting $setting): float
    {
        $passingPercentage = (float) $setting->passing_percentage;

        if ($percentage < $passingPercentage) {
            return round((float) $setting->failing_grade, 2);
        }

        $range = max(0.01, 100 - $passingPercentage);
        $grade = (float) $setting->highest_grade
            + ((100 - min(100, $percentage)) / $range)
            * ((float) $setting->passing_grade - (float) $setting->highest_grade);

        return round(max((float) $setting->highest_grade, min((float) $setting->passing_grade, $grade)), 2);
    }

    public function defaultSettings(): array
    {
        return [
            'passing_percentage' => SystemSetting::defaultPassingPercentage(),
            'highest_grade' => 1,
            'passing_grade' => SystemSetting::defaultPassingGrade(),
            'failing_grade' => 5,
        ];
    }

    /** @return array{key: string, label: string} */
    private function progressStatus(int $graded, int $total, ?float $percentage, ?float $currentPercentage, float $passingPercentage): array
    {
        if ($total === 0) {
            return ['key' => 'no_items', 'label' => 'No score items'];
        }
        if ($graded === 0) {
            return ['key' => 'not_started', 'label' => 'Not started'];
        }
        if ($graded < $total) {
            return ($currentPercentage ?? 0) >= $passingPercentage
                ? ['key' => 'on_track', 'label' => 'On track']
                : ['key' => 'at_risk', 'label' => 'At risk'];
        }

        return ($percentage ?? 0) >= $passingPercentage
            ? ['key' => 'completed', 'label' => 'Completed']
            : ['key' => 'needs_improvement', 'label' => 'Needs improvement'];
    }

    private function ensureStructure(int $sectionId): void
    {
        GradingSetting::firstOrCreate(['section_id' => $sectionId], $this->defaultSettings());
        $defaults = [
            'activity' => ['name' => 'Class Standing', 'weight' => 20, 'color' => '#f59e0b', 'sort_order' => 0],
            'project' => ['name' => 'Requirements', 'weight' => 30, 'color' => '#db2777', 'sort_order' => 1],
            'exam' => ['name' => 'Term Test', 'weight' => 30, 'color' => '#16a34a', 'sort_order' => 2],
            'quiz' => ['name' => 'Quizzes', 'weight' => 20, 'color' => '#2563eb', 'sort_order' => 3],
        ];

        if (! GradingCategory::where('section_id', $sectionId)->exists()) {
            foreach ($defaults as $default) {
                GradingCategory::create(['section_id' => $sectionId, ...$default]);
            }
        }

        $categories = GradingCategory::where('section_id', $sectionId)->orderBy('sort_order')->get();
        foreach ($defaults as $type => $default) {
            $category = $categories->firstWhere('sort_order', $default['sort_order']) ?? $categories->first();
            if (! $category) {
                continue;
            }
            Assessment::where('section_id', $sectionId)->where('type', $type)->whereNull('grading_category_id')
                ->update(['grading_category_id' => $category->id]);
        }
    }
}
