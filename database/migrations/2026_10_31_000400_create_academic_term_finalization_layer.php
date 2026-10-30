<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table): void {
            $table->timestamp('grade_submission_deadline')->nullable()->after('ends_on');
            $table->foreignId('finalized_by')->nullable()->after('is_current')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedSmallInteger('snapshot_version')->default(0);
        });

        Schema::create('term_grade_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at');
            $table->timestamps();
            $table->unique(['academic_term_id', 'section_id']);
        });

        Schema::create('academic_term_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_term_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->json('payload');
            $table->char('checksum', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academic_term_id', 'version']);
        });

        Schema::create('completion_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('nstp_enrollments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->string('status', 20);
            $table->decimal('final_percentage', 6, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_term_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completion_clearances');
        Schema::dropIfExists('academic_term_snapshots');
        Schema::dropIfExists('academic_term_transitions');
        Schema::dropIfExists('term_grade_approvals');
        Schema::table('academic_terms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn(['grade_submission_deadline', 'finalized_at', 'archived_at', 'snapshot_version']);
        });
    }
};
