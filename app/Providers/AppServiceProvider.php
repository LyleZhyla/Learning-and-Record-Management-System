<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Models\StudentNotification;
use App\Services\NotificationService;
use App\Services\PortalAccessService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.compact');

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

                if ($enrollment?->section_id) {
                    $pendingAssessmentCount = Assessment::query()
                        ->where('section_id', $enrollment->section_id)
                        ->where('status', 'published')
                        ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $user->id))
                        ->count();
                }

                $eventCounts = StudentNotification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->selectRaw('type, COUNT(*) as total')
                    ->groupBy('type')
                    ->pluck('total', 'type');
                $sidebarNotificationCounts = [
                    'announcements' => app(NotificationService::class)->visibleQuery($user)
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

        View::composer(['layouts.student', 'layouts.facilitator'], function ($view): void {
            $user = auth()->user();
            $sidebarUnreadMessageCount = $user?->receivedChatMessages()->whereNull('read_at')->count() ?? 0;

            $view->with('sidebarUnreadMessageCount', $sidebarUnreadMessageCount);
        });
    }
}
