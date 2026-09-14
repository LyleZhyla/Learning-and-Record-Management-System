<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('nstp_components')->cascadeOnDelete();
            $table->string('academic_year', 9);
            $table->string('semester', 20);
            $table->unsignedTinyInteger('day_of_week')->default(6);
            $table->time('day_start')->default('08:00:00');
            $table->time('day_end')->default('17:00:00');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->unsignedSmallInteger('session_minutes')->default(240);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['component_id', 'academic_year', 'semester'], 'schedule_settings_term_unique');
        });

        Schema::create('section_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->unique()->constrained('nstp_sections')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_automatic')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_schedules');
        Schema::dropIfExists('schedule_settings');
    }
};
