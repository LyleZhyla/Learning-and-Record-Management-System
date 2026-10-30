<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Models\Campus;
use App\Models\ChatGroupMessage;
use App\Models\Institution;
use App\Models\NotificationRule;
use App\Models\Scopes\InstitutionScope;
use App\Models\StudentNotification;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\NotificationService;
use App\Services\PortalAccessService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InstitutionContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $context = $this->app->make(InstitutionContext::class);
        foreach (config('multitenancy.models', []) as $modelClass) {
            $modelClass::addGlobalScope('institution', new InstitutionScope($context));
            $modelClass::creating(function ($model) use ($context): void {
                if (! Schema::hasColumn($model->getTable(), 'institution_id')) {
                    return;
                }
                if ($model instanceof User && $model->getAttribute('is_system_admin')) {
                    return;
                }
                $institutionId = $model->getAttribute('institution_id') ?: $context->id();
                if (! $institutionId && Schema::hasTable('institutions')) {
                    $institutionId = Institution::query()->where('is_active', true)->orderBy('id')->value('id');
                }
                if ($institutionId && ! $model->getAttribute('institution_id')) {
                    $model->setAttribute('institution_id', $institutionId);
                }
                if ($institutionId && Schema::hasColumn($model->getTable(), 'campus_id') && ! $model->getAttribute('campus_id')) {
                    $campusId = Campus::query()->where('institution_id', $institutionId)
                        ->where('is_active', true)->orderByDesc('is_main')->orderBy('id')->value('id');
                    if ($campusId) {
                        $model->setAttribute('campus_id', $campusId);
                    }
                }
            });
        }

        Paginator::defaultView('pagination.compact');
        Blade::if('permission', fn (string $permission): bool => auth()->user()?->hasPermission($permission) === true);

        View::composer('layouts.student', function ($view): void {
            $user = auth()->user();
            $pendingAssessmentCount = 0;
            $sidebarNotificationCounts = [
                'announcements' => 0,
                'materials' => 0,
                'assessments' => 0,
                'attendance' => 0,
            ];

            if ($user?->isStudent()) {
                $enrollment = app(PortalAccessService::class)->currentEnrollment($user);

                if ($enrollment?->section_id && NotificationRule::configured(StudentNotification::ASSESSMENT)->usesChannel('sidebar')) {
                    $pendingAssessmentCount = Assessment::query()
                        ->where('section_id', $enrollment->section_id)
                        ->where('status', 'published')
                        ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $user->id))
                        ->count();
                }

                $sidebarEventTypes = collect([
                    StudentNotification::MATERIAL,
                    StudentNotification::ASSESSMENT,
                    StudentNotification::LATE_ATTENDANCE,
                    StudentNotification::ABSENT_ATTENDANCE,
                ])->filter(fn (string $type): bool => NotificationRule::configured($type)->usesChannel('sidebar'));
                $eventCounts = StudentNotification::query()
                    ->where('user_id', $user->id)
                    ->whereIn('type', $sidebarEventTypes)
                    ->available()
                    ->whereNull('read_at')
                    ->selectRaw('type, COUNT(*) as total')
                    ->groupBy('type')
                    ->pluck('total', 'type');
                $sidebarNotificationCounts = [
                    'announcements' => app(NotificationService::class)->notificationQuery($user, 'sidebar')
                        ->whereDoesntHave('readers', fn ($readers) => $readers->whereKey($user->id))
                        ->count(),
                    'materials' => (int) ($eventCounts[StudentNotification::MATERIAL] ?? 0),
                    'assessments' => (int) ($eventCounts[StudentNotification::ASSESSMENT] ?? 0),
                    'attendance' => (int) ($eventCounts[StudentNotification::LATE_ATTENDANCE] ?? 0)
                        + (int) ($eventCounts[StudentNotification::ABSENT_ATTENDANCE] ?? 0),
                ];
            }

            $view->with('sidebarPendingAssessmentCount', $pendingAssessmentCount);
            $view->with('sidebarNotificationCounts', $sidebarNotificationCounts);
        });

        View::composer(['layouts.admin', 'layouts.nstp-admin', 'layouts.coordinator', 'layouts.facilitator', 'layouts.student'], function ($view): void {
            $user = auth()->user();
            $institutionContext = app(InstitutionContext::class);
            $view->with('activeInstitution', $institutionContext->institution());
            $view->with('switchableInstitutions', $user?->isSystemAdmin()
                ? Institution::query()->where('is_active', true)->orderBy('name')->get()
                : collect());
            $messageRule = NotificationRule::configured('message');
            $messageAvailableBefore = $messageRule->schedule_mode === 'delayed'
                ? now()->subMinutes(max(1, $messageRule->delay_minutes))
                : now();
            $sidebarUnreadMessageCount = $user && $messageRule->usesChannel('sidebar')
                ? $user->receivedChatMessages()->whereNull('read_at')->where('created_at', '<=', $messageAvailableBefore)->count()
                    + ChatGroupMessage::unreadFor($user)->where('chat_group_messages.created_at', '<=', $messageAvailableBefore)->count()
                : 0;

            $view->with('sidebarUnreadMessageCount', $sidebarUnreadMessageCount);
        });

        View::composer(['layouts.admin', 'layouts.nstp-admin', 'layouts.coordinator', 'layouts.facilitator'], function ($view): void {
            $user = auth()->user();
            $counts = ['announcements' => 0, 'materials' => 0, 'assessments' => 0, 'attendance' => 0];

            if ($user) {
                $sidebarEventTypes = collect([
                    StudentNotification::MATERIAL,
                    StudentNotification::ASSESSMENT,
                    StudentNotification::LATE_ATTENDANCE,
                    StudentNotification::ABSENT_ATTENDANCE,
                ])->filter(fn (string $type): bool => NotificationRule::configured($type)->usesChannel('sidebar'));
                $eventCounts = StudentNotification::query()
                    ->where('user_id', $user->id)
                    ->whereIn('type', $sidebarEventTypes)
                    ->available()
                    ->whereNull('read_at')
                    ->selectRaw('type, COUNT(*) as total')
                    ->groupBy('type')
                    ->pluck('total', 'type');
                $counts = [
                    'announcements' => app(NotificationService::class)->notificationQuery($user, 'sidebar')
                        ->whereDoesntHave('readers', fn ($readers) => $readers->whereKey($user->id))
                        ->count(),
                    'materials' => (int) ($eventCounts[StudentNotification::MATERIAL] ?? 0),
                    'assessments' => (int) ($eventCounts[StudentNotification::ASSESSMENT] ?? 0),
                    'attendance' => (int) ($eventCounts[StudentNotification::LATE_ATTENDANCE] ?? 0)
                        + (int) ($eventCounts[StudentNotification::ABSENT_ATTENDANCE] ?? 0),
                ];
            }

            $view->with('sidebarPortalNotificationCounts', $counts);
        });
    }
}
