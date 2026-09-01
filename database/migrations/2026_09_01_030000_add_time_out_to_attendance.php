<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('scan_mode', 20)->default('time_in')->after('status');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dateTime('checked_out_at')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('checked_out_at');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn('scan_mode');
        });
    }
};
