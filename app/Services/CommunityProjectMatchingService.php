<?php

namespace App\Services;

use App\Models\CommunityNeedPriority;
use Illuminate\Support\Str;

class CommunityProjectMatchingService
{
    /** @return array{score: int, snapshot: array<string, mixed>} */
    public function score(CommunityNeedPriority $need, string $location, float $budget, array $availableSkills): array
    {
        $requiredSkills = collect($need->required_skills ?? [])->map(fn ($skill) => Str::lower(trim($skill)))->filter()->unique();
        $skills = collect($availableSkills)->map(fn ($skill) => Str::lower(trim($skill)))->filter()->unique();
        $matchedSkills = $requiredSkills->intersect($skills)->values();

        $locationScore = $this->locationScore($need->location, $location);
        $budgetScore = $need->estimated_budget === null
            ? 15
            : ($budget <= (float) $need->estimated_budget
                ? 25
                : max(0, (int) round(25 * ((float) $need->estimated_budget / max($budget, 1)))));
        $skillsScore = $requiredSkills->isEmpty()
            ? 30
            : (int) round(30 * ($matchedSkills->count() / $requiredSkills->count()));
        $score = min(100, 20 + $locationScore + $budgetScore + $skillsScore);

        return [
            'score' => $score,
            'snapshot' => [
                'need_priority_id' => $need->id,
                'need' => $need->problem_title,
                'sdg_codes' => $need->sdg_codes,
                'location' => ['project' => $location, 'need' => $need->location, 'score' => $locationScore, 'maximum' => 25],
                'budget' => ['project' => $budget, 'need_estimate' => $need->estimated_budget, 'score' => $budgetScore, 'maximum' => 25],
                'skills' => ['available' => $skills->values()->all(), 'required' => $requiredSkills->values()->all(), 'matched' => $matchedSkills->all(), 'score' => $skillsScore, 'maximum' => 30],
                'need_alignment' => ['score' => 20, 'maximum' => 20],
                'scored_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function locationScore(string $needLocation, string $projectLocation): int
    {
        $need = Str::lower(trim($needLocation));
        $project = Str::lower(trim($projectLocation));
        if ($need === $project || Str::contains($project, $need) || Str::contains($need, $project)) {
            return 25;
        }

        $needTokens = collect(preg_split('/[^a-z0-9]+/', $need) ?: [])->filter(fn ($token) => strlen($token) > 2)->unique();
        if ($needTokens->isEmpty()) {
            return 0;
        }

        $projectTokens = collect(preg_split('/[^a-z0-9]+/', $project) ?: [])->filter()->unique();

        return (int) round(25 * ($needTokens->intersect($projectTokens)->count() / $needTokens->count()));
    }
}
