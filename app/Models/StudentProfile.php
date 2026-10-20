<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (StudentProfile $profile): void {
            $user = $profile->user;

            if ($profile->wasChanged('last_name') || $profile->wasRecentlyCreated) {
                $user->update(['last_name_sort_key' => strtolower(trim($profile->last_name))]);
            }

            if ($user->must_complete_student_profile && $user->hasCompleteStudentProfile()) {
                $user->update(['must_complete_student_profile' => false]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'emergency_same_address' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(StudentRegistration::class, 'student_registration_id');
    }
}
