<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSubmission extends Model
{
    protected $fillable = [
        'assessment_id', 'student_id', 'answer_text', 'file_path', 'original_filename', 'submitted_at',
        'score', 'feedback', 'graded_by', 'graded_at', 'ai_suggested_score', 'ai_feedback',
        'ai_breakdown', 'ai_confidence', 'ai_model', 'ai_generated_at', 'ai_approved_by', 'ai_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'graded_at' => 'datetime',
            'ai_suggested_score' => 'decimal:2',
            'ai_breakdown' => 'array',
            'ai_confidence' => 'decimal:2',
            'ai_generated_at' => 'datetime',
            'ai_approved_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function aiApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_approved_by');
    }
}
