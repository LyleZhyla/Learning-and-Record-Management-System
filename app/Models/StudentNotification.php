<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentNotification extends Model
{
    public const MATERIAL = 'learning_material';

    public const ASSESSMENT = 'assessment';

    public const LATE_ATTENDANCE = 'late_attendance';

    public const ABSENT_ATTENDANCE = 'absent_attendance';

    protected $fillable = ['user_id', 'type', 'source_id', 'title', 'body', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): string
    {
        return match ($this->type) {
            self::MATERIAL => route('student.materials.index'),
            self::ASSESSMENT => route('student.assessments.show', $this->source_id),
            self::LATE_ATTENDANCE, self::ABSENT_ATTENDANCE => route('student.attendance.index'),
            default => route('student.dashboard'),
        };
    }
}
