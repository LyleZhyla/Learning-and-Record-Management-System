<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nstp_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_section_capacity')->default(40);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('nstp_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('academic_year', 9);
            $table->string('semester', 20);
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->unique(['academic_year', 'semester', 'code'], 'nstp_sections_term_code_unique');
            $table->index(['component_id', 'academic_year', 'semester'], 'nstp_sections_term_index');
        });

        Schema::create('nstp_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('nstp_sections')->nullOnDelete();
            $table->string('academic_year', 9);
            $table->string('semester', 20);
            $table->string('status', 20)->default('enrolled')->index();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year', 'semester'], 'nstp_enrollments_student_term_unique');
            $table->index(['component_id', 'academic_year', 'semester'], 'nstp_enrollments_term_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nstp_enrollments');
        Schema::dropIfExists('nstp_sections');
        Schema::dropIfExists('nstp_components');
    }
};
