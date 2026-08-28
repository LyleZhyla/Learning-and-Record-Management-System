<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->dateTime('starts_at');
            $table->dateTime('late_after')->nullable();
            $table->dateTime('ends_at');
            $table->string('token', 64)->unique();
            $table->text('qr_payload');
            $table->longText('qr_svg');
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();

            $table->index(['section_id', 'starts_at']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->dateTime('checked_in_at')->nullable();
            $table->string('source', 20)->default('qr');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id'], 'attendance_records_session_student_unique');
        });

        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('nstp_sections')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('external_url')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();

            $table->index(['component_id', 'section_id', 'status'], 'learning_materials_audience_index');
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->string('type', 30)->default('activity');
            $table->longText('instructions')->nullable();
            $table->decimal('max_score', 8, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(10);
            $table->dateTime('due_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();

            $table->index(['section_id', 'status', 'due_at']);
        });

        Schema::create('assessment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->dateTime('submitted_at');
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id'], 'assessment_submissions_assessment_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_submissions');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('learning_materials');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
