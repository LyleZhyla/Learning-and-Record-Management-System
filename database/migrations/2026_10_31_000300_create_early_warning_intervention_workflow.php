<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_thresholds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('nstp_components')->cascadeOnDelete();
            $table->decimal('minimum_attendance_percentage', 5, 2)->default(75);
            $table->decimal('minimum_grade_percentage', 5, 2)->default(75);
            $table->unsignedSmallInteger('maximum_missing_requirements')->default(0);
            $table->unsignedSmallInteger('follow_up_days')->default(7);
            $table->unsignedSmallInteger('escalation_days')->default(14);
            $table->unsignedSmallInteger('minimum_improvement_points')->default(5);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'component_id']);
        });

        Schema::create('student_risk_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('nstp_components')->cascadeOnDelete();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('status', 30);
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->decimal('grade_percentage', 5, 2)->nullable();
            $table->unsignedSmallInteger('missing_requirements')->default(0);
            $table->json('reasons')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['student_id', 'section_id', 'calculated_at']);
        });

        Schema::create('student_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->foreignId('risk_snapshot_id')->nullable()->constrained('student_risk_snapshots')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->string('referral_type', 80)->default('academic_support');
            $table->string('severity', 20)->default('medium');
            $table->text('notes')->nullable();
            $table->text('action_plan')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('resolution')->nullable();
            $table->unsignedSmallInteger('escalation_count')->default(0);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'section_id', 'status']);
        });

        Schema::create('intervention_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intervention_id')->constrained('student_interventions')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->text('details')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();
        });

        $rule = config('notification_rules.early_warning');
        foreach (DB::table('institutions')->pluck('id') as $institutionId) {
            DB::table('notification_rules')->updateOrInsert(
                ['institution_id' => $institutionId, 'event_key' => 'early_warning'],
                [
                    'name' => $rule['name'], 'description' => $rule['description'], 'is_enabled' => true,
                    'channels' => json_encode(['bell', 'sidebar'], JSON_THROW_ON_ERROR),
                    'title_template' => $rule['title_template'], 'body_template' => $rule['body_template'],
                    'schedule_mode' => 'immediate', 'delay_minutes' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('notification_rules')->where('event_key', 'early_warning')->delete();
        Schema::dropIfExists('intervention_activities');
        Schema::dropIfExists('student_interventions');
        Schema::dropIfExists('student_risk_snapshots');
        Schema::dropIfExists('risk_thresholds');
    }
};
