<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\User;

class GradeService
{
    public function summary(User $student, int $sectionId): array
    {
        $assessments = Assessment::with(['submissions' => fn ($query) => $query->where('student_id', $student->id)])
            ->where('section_id', $sectionId)
            ->where('status', 'published')
            ->orderBy('due_at')
            ->get();

        $totalWeight = (float) $assessments->sum('weight');
        $earnedWeight = 0.0;
        $graded = 0;

        foreach ($assessments as $assessment) {
            $submission = $assessment->submissions->first();
            if ($submission?->score !== null && (float) $assessment->max_score > 0) {
                $earnedWeight += ((float) $submission->score / (float) $assessment->max_score) * (float) $assessment->weight;
                $graded++;
            }
        }

        return [
            'assessments' => $assessments,
            'grade' => $totalWeight > 0 ? round(($earnedWeight / $totalWeight) * 100, 2) : null,
            'graded_count' => $graded,
            'total_count' => $assessments->count(),
            'total_weight' => $totalWeight,
        ];
    }
}
