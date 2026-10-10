<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationResponse extends Model
{
    public const TYPES = [
        'student_instructor' => 'Student evaluation of instructor',
        'instructor_student' => 'Instructor evaluation of student',
        'community_feedback' => 'Community-beneficiary feedback',
    ];

    public const CRITERIA = [
        'student_instructor' => [
            'clarity' => 'Explains lessons and instructions clearly',
            'preparedness' => 'Comes prepared and organized',
            'engagement' => 'Encourages participation and engagement',
            'fairness' => 'Treats students fairly and respectfully',
            'support' => 'Provides useful guidance and support',
        ],
        'instructor_student' => [
            'participation' => 'Attendance and active participation',
            'teamwork' => 'Teamwork and cooperation',
            'responsibility' => 'Reliability and task completion',
            'communication' => 'Communication with the team and community',
            'community_engagement' => 'Community engagement and service attitude',
        ],
        'community_feedback' => [
            'relevance' => 'Relevance to community needs',
            'organization' => 'Organization of activities',
            'responsiveness' => 'Responsiveness of the NSTP team',
            'impact' => 'Positive impact on beneficiaries',
            'sustainability' => 'Potential for lasting benefit',
        ],
    ];

    protected $fillable = [
        'type', 'section_id', 'community_project_id', 'evaluator_id', 'subject_user_id',
        'respondent_name', 'respondent_relationship', 'answers', 'comments', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['answers' => 'array', 'submitted_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(NstpSection::class, 'section_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(CommunityProject::class, 'community_project_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function averageRating(): float
    {
        return round((float) collect($this->answers)->average(), 2);
    }
}
