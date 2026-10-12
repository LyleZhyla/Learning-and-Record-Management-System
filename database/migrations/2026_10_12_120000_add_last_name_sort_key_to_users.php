<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('last_name_sort_key')->nullable()->after('name')->index();
        });

        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'last_name_sort_key' => $this->sortKey($user->name),
                ]);
            }
        });

        DB::table('student_profiles')->select(['user_id', 'last_name'])->orderBy('user_id')->chunkById(200, function ($profiles): void {
            foreach ($profiles as $profile) {
                DB::table('users')->where('id', $profile->user_id)->update([
                    'last_name_sort_key' => Str::lower(trim($profile->last_name)),
                ]);
            }
        }, 'user_id');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_name_sort_key']);
            $table->dropColumn('last_name_sort_key');
        });
    }

    private function sortKey(string $name): string
    {
        $name = trim($name);
        if (str_contains($name, ',')) {
            return Str::lower(trim(Str::before($name, ',')));
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $suffixes = ['jr', 'sr', 'ii', 'iii', 'iv', 'v', 'md', 'dvm', 'dds', 'phd', 'rn', 'cpa', 'esq'];
        while (count($parts) > 1 && in_array(Str::lower(trim(end($parts), '.,')), $suffixes, true)) {
            array_pop($parts);
        }

        return Str::lower((string) end($parts));
    }
};
