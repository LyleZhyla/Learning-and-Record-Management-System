<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_registrations')
            ->select(['id', 'email', 'last_name'])
            ->orderBy('id')
            ->chunkById(200, function ($registrations): void {
                foreach ($registrations as $registration) {
                    DB::table('users')
                        ->where('role', 'student')
                        ->whereRaw('LOWER(email) = ?', [Str::lower($registration->email)])
                        ->update(['last_name_sort_key' => Str::lower(trim($registration->last_name))]);
                }
            });

        DB::table('student_profiles')
            ->select(['id', 'user_id', 'last_name'])
            ->orderBy('id')
            ->chunkById(200, function ($profiles): void {
                foreach ($profiles as $profile) {
                    DB::table('users')->where('id', $profile->user_id)->update([
                        'last_name_sort_key' => Str::lower(trim($profile->last_name)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The original name-derived values remain valid fallback sort keys.
    }
};
