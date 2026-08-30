<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Services\PortalAccessService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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

            if ($user?->isStudent()) {
                $enrollment = app(PortalAccessService::class)->currentEnrollment($user);

                if ($enrollment?->section_id) {
                    $pendingAssessmentCount = Assessment::query()
                        ->where('section_id', $enrollment->section_id)
                        ->where('status', 'published')
                        ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $user->id))
                        ->count();
                }
            }

            $view->with('sidebarPendingAssessmentCount', $pendingAssessmentCount);
        });
    }
}
