<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function inactivityTimeoutMinutes(): int
    {
        return max(1, min(1440, (int) static::query()
            ->where('key', 'inactivity_timeout_minutes')
            ->value('value') ?: 30));
    }

    public static function componentSelectionIsOpen(): bool
    {
        return static::query()
            ->where('key', 'component_selection_open')
            ->value('value') !== '0';
    }

    public static function studentRegistrationIsOpen(): bool
    {
        return static::query()->where('key', 'student_registration_open')->value('value') !== '0';
    }

    public static function studentRegistrationAcademicYear(): string
    {
        $startYear = now()->month >= 6 ? now()->year : now()->year - 1;

        return static::query()->where('key', 'student_registration_academic_year')->value('value')
            ?: $startYear.'-'.($startYear + 1);
    }

    public static function studentRegistrationSemester(): string
    {
        return static::query()->where('key', 'student_registration_semester')->value('value')
            ?: (now()->month >= 6 ? 'first' : 'second');
    }

    public static function defaultPassingPercentage(): float
    {
        return max(1, min(99.99, (float) (static::query()
            ->where('key', 'default_passing_percentage')
            ->value('value') ?: 75)));
    }

    public static function defaultPassingGrade(): float
    {
        return max(1.01, min(4.99, (float) (static::query()
            ->where('key', 'default_passing_grade')
            ->value('value') ?: 3)));
    }

    public static function requiredDocumentsAreEnforced(): bool
    {
        return static::query()
            ->where('key', 'required_documents_enforced')
            ->value('value') !== '0';
    }
}
