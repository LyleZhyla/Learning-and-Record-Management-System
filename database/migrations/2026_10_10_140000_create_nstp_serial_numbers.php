<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nstp_serial_number_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->string('academic_year', 9);
            $table->string('semester', 20);
            $table->date('received_at');
            $table->string('source_file_path');
            $table->string('source_file_original_name');
            $table->string('source_file_mime_type')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['component_id', 'academic_year', 'semester'], 'serial_release_term_index');
        });

        Schema::create('nstp_student_serial_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained('nstp_serial_number_releases')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->unique()->constrained('nstp_enrollments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'release_id'], 'student_serial_release_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nstp_student_serial_numbers');
        Schema::dropIfExists('nstp_serial_number_releases');
    }
};
