<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_complete_student_profile')
                ->default(true)
                ->after('must_change_password');
        });

        DB::table('users')
            ->where('role', 'student')
            ->whereIn('id', function ($query): void {
                $query->select('user_id')
                    ->from('student_profiles')
                    ->whereNotNull('major')
                    ->where('major', '<>', '')
                    ->where(function ($profileQuery): void {
                        $profileQuery->where('emergency_same_address', true)
                            ->orWhere(function ($addressQuery): void {
                                $addressQuery->whereNotNull('emergency_address')
                                    ->where('emergency_address', '<>', '');
                            });
                    });
            })
            ->update(['must_complete_student_profile' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_complete_student_profile');
        });
    }
};
