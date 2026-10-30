<?php

use App\Models\Institution;
use App\Models\NstpEnrollment;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\ProgressMonitoringService;
use App\Services\RetentionPolicyService;
use Database\Seeders\SampleAccountsSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:remove-accounts', function () {
    $emails = array_column(SampleAccountsSeeder::ACCOUNTS, 'email');
    $userIds = User::whereIn('email', $emails)->pluck('id');

    DB::transaction(function () use ($emails, $userIds): void {
        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        User::whereIn('email', $emails)->delete();
    });

    $this->info($userIds->count().' temporary sample account(s) removed.');
})->purpose('Remove only the temporary Smart NSTP sample accounts');

Artisan::command('privacy:enforce-retention', function (InstitutionContext $context, RetentionPolicyService $service) {
    $affected = 0;
    Institution::query()->where('is_active', true)->each(function (Institution $institution) use ($context, $service, &$affected): void {
        $context->runFor($institution->id, function () use ($service, &$affected): void {
            RetentionPolicy::query()->where('is_active', true)->each(function (RetentionPolicy $policy) use ($service, &$affected): void {
                $affected += $service->enforce($policy);
            });
        });
    });
    $this->info("Retention enforcement complete. {$affected} record(s) affected.");
})->purpose('Enforce institution-specific privacy retention policies');

Schedule::command('privacy:enforce-retention')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('risk:scan', function (InstitutionContext $context, ProgressMonitoringService $progress) {
    $scanned = 0;
    Institution::query()->where('is_active', true)->each(function (Institution $institution) use ($context, $progress, &$scanned): void {
        $context->runFor($institution->id, function () use ($progress, &$scanned): void {
            NstpEnrollment::query()->with('student')->where('status', 'enrolled')->whereNotNull('section_id')
                ->each(function (NstpEnrollment $enrollment) use ($progress, &$scanned): void {
                    $progress->summary($enrollment->student, $enrollment->section_id);
                    $scanned++;
                });
        });
    });
    $this->info("Early-warning scan complete. {$scanned} enrollment(s) evaluated.");
})->purpose('Evaluate student risk thresholds and synchronize intervention cases');

Schedule::command('risk:scan')->hourly()->withoutOverlapping();
