<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Database\Seeders\SampleAccountsSeeder;
use App\Models\User;

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
