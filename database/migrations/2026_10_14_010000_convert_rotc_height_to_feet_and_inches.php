<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rotc_height_feet')->nullable()->after('rotc_category');
            $table->unsignedTinyInteger('rotc_height_inches')->nullable()->after('rotc_height_feet');
        });

        DB::table('nstp_enrollments')
            ->whereNotNull('rotc_height_cm')
            ->orderBy('id')
            ->chunkById(100, function ($enrollments): void {
                foreach ($enrollments as $enrollment) {
                    $totalInches = (int) round($enrollment->rotc_height_cm / 2.54);

                    DB::table('nstp_enrollments')
                        ->where('id', $enrollment->id)
                        ->update([
                            'rotc_height_feet' => intdiv($totalInches, 12),
                            'rotc_height_inches' => $totalInches % 12,
                        ]);
                }
            });

        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->dropColumn('rotc_height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rotc_height_cm')->nullable()->after('rotc_category');
        });

        DB::table('nstp_enrollments')
            ->whereNotNull('rotc_height_feet')
            ->whereNotNull('rotc_height_inches')
            ->orderBy('id')
            ->chunkById(100, function ($enrollments): void {
                foreach ($enrollments as $enrollment) {
                    DB::table('nstp_enrollments')
                        ->where('id', $enrollment->id)
                        ->update([
                            'rotc_height_cm' => (int) round((($enrollment->rotc_height_feet * 12) + $enrollment->rotc_height_inches) * 2.54),
                        ]);
                }
            });

        Schema::table('nstp_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['rotc_height_feet', 'rotc_height_inches']);
        });
    }
};
