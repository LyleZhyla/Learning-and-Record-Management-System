<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->string('blood_type', 10)->change();
        });

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('blood_type', 10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_registrations', function (Blueprint $table): void {
            $table->string('blood_type', 5)->change();
        });

        Schema::table('student_profiles', function (Blueprint $table): void {
            $table->string('blood_type', 5)->change();
        });
    }
};
