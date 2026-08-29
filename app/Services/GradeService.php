<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\GradingCategory;
use App\Models\GradingSetting;
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

        $categorySummaries = $categories->map(function (GradingCategory $category) use (&$earnedPercentage, &$graded, &$total, &$rawEarned, &$rawMaximum) {
            $maximum = (float) $category->assessments->sum('max_score');
            $earned = 0.0;
            $categoryGraded = 0;

            foreach ($category->assessments as $assessment) {
                $submission = $assessment->submissions->first();
                if ($submission?->score !== null) {
                    $earned += (float) $submission->score;
                    $rawEarned += (float) $submission->score;
                    $rawMaximum += (float) $assessment->max_score;
                    $categoryGraded++;
                }
            }

            $weighted = $maximum > 0 ? ($earned / $maximum) * (float) $category->weight : 0.0;
            $earnedPercentage += $weighted;
            $graded += $categoryGraded;
            $total += $category->assessments->count();

            return [
                'category' => $category,
                'earned' => round($earned, 2),
                'maximum' => round($maximum, 2),
                'weighted_score' => round($weighted, 2),
                'graded_count' => $categoryGraded,
                'total_count' => $category->assessments->count(),
            ];
        });

        $percentage = $graded > 0 ? round($earnedPercentage, 2) : null;

        return [
            'assessments' => $categories->flatMap->assessments,
            'categories' => $categorySummaries,
            'grade' => $percentage === null ? null : $this->transmute($percentage, $setting),
            'percentage' => $percentage,
            'raw_percentage' => $graded > 0 && $rawMaximum > 0 ? round(($rawEarned / $rawMaximum) * 100, 2) : null,
            'graded_count' => $graded,
            'total_count' => $total,
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
            'passing_percentage' => 75,
            'highest_grade' => 1,
            'passing_grade' => 3,
            'failing_grade' => 5,
        ];
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
