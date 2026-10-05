<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->string('academic_year', 9)->nullable()->after('status')->index();
            $table->string('semester', 20)->nullable()->after('academic_year')->index();
            $table->string('nstp_level', 20)->default('nstp_1')->after('semester')->index();
        });

        $startYear = now()->month >= 6 ? now()->year : now()->year - 1;
        $academicYear = $startYear.'-'.($startYear + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';

        foreach ([
            'student_registration_open' => '1',
            'student_registration_academic_year' => $academicYear,
            'student_registration_semester' => $semester,
        ] as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_by' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'student_registration_open',
            'student_registration_academic_year',
            'student_registration_semester',
        ])->delete();

        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->dropColumn(['academic_year', 'semester', 'nstp_level']);
        });
    }
};
