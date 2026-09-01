<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
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
        'nstp_component_id',
        'student_qr_token',
        'profile_photo_path',
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
        'student_qr_token',
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

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role === 'student' && blank($user->student_qr_token)) {
                $user->student_qr_token = Str::random(48);
            }
        });
    }

    public function studentQrPayload(): string
    {
        abort_unless($this->isStudent() && filled($this->student_qr_token), 404);

        return 'SNAPIE:STUDENT:'.$this->student_qr_token;
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

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
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
            'coordinator' => 'coordinator.dashboard',
            'facilitator' => 'facilitator.dashboard',
            'student' => 'student.dashboard',
            default => null,
        };
    }

    public function nstpEnrollments(): HasMany
    {
        return $this->hasMany(NstpEnrollment::class, 'student_id');
    }

    public function latestNstpEnrollment(): HasOne
    {
        return $this->hasOne(NstpEnrollment::class, 'student_id')->latestOfMany();
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function nstpComponent(): BelongsTo
    {
        return $this->belongsTo(NstpComponent::class, 'nstp_component_id');
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

    public function sentChatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function receivedChatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'recipient_id');
    }

    public function aiChatMessages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class);
    }

    public function aiChatConversations(): HasMany
    {
        return $this->hasMany(AiChatConversation::class);
    }
}
