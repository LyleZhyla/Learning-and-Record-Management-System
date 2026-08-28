<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_LABELS = [
        'student' => 'Student',
        'facilitator' => 'Facilitator',
        'coordinator' => 'Coordinator',
        'nstp_admin' => 'NSTP Admin',
        'super_admin' => 'Super Admin',
    ];

    public const STATUS_LABELS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'must_change_password',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isNstpAdmin(): bool
    {
        return $this->role === 'nstp_admin';
    }

    public function isFacilitator(): bool
    {
        return $this->role === 'facilitator';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? str($this->role)->headline()->toString();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function dashboardRouteName(): ?string
    {
        return match ($this->role) {
            'super_admin' => 'admin.dashboard',
            'nstp_admin' => 'nstp_admin.dashboard',
            'facilitator' => 'facilitator.dashboard',
            'student' => 'student.dashboard',
            default => null,
        };
    }

    public function nstpEnrollments(): HasMany
    {
        return $this->hasMany(NstpEnrollment::class, 'student_id');
    }

    public function facilitatedSections(): HasMany
    {
        return $this->hasMany(NstpSection::class, 'facilitator_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'student_id');
    }

    public function assessmentSubmissions(): HasMany
    {
        return $this->hasMany(AssessmentSubmission::class, 'student_id');
    }
}
