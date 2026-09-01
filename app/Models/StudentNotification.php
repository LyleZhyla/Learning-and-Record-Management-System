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

    public function destination(User $user): string
    {
        $prefix = match ($user->role) {
            'super_admin' => 'admin',
            'nstp_admin' => 'nstp_admin',
            default => $user->role,
        };

        return match ($this->type) {
            self::MATERIAL => $user->isCoordinator()
                ? route('coordinator.dashboard')
                : route($prefix.'.materials.index'),
            self::ASSESSMENT => $this->assessmentDestination($user, $prefix),
            self::LATE_ATTENDANCE, self::ABSENT_ATTENDANCE => $this->attendanceDestination($user, $prefix),
            default => route($user->dashboardRouteName()),
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->type) {
            self::MATERIAL => 'Learning Materials',
            self::ASSESSMENT => 'Assessments',
            default => 'Attendance',
        };
    }

    private function assessmentDestination(User $user, string $prefix): string
    {
        if ($user->isCoordinator()) {
            $sectionId = Assessment::whereKey($this->source_id)->value('section_id');

            return route('coordinator.grades.index', array_filter(['section' => $sectionId]));
        }

        return route($prefix.'.assessments.show', $this->source_id);
    }

    private function attendanceDestination(User $user, string $prefix): string
    {
        if ($user->isStudent()) {
            return route('student.attendance.index');
        }

        $sessionId = AttendanceRecord::whereKey($this->source_id)->value('attendance_session_id');

        return $sessionId
            ? route($prefix.'.attendance.show', $sessionId)
            : route($prefix.'.attendance.index');
    }
}
