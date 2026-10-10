<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number', 40)->nullable()->unique();
            $table->string('title');
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('nstp_sections')->nullOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->text('objectives');
            $table->text('beneficiaries');
            $table->unsignedInteger('beneficiary_count')->nullable();
            $table->string('location');
            $table->decimal('budget', 12, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('approval_status', 30)->default('pending')->index();
            $table->text('approval_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('implementation_status', 30)->default('proposed')->index();
            $table->text('implementation_notes')->nullable();
            $table->timestamps();
            $table->index(['component_id', 'section_id']);
        });

        Schema::create('community_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->text('accomplishment_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_project_activities');
        Schema::dropIfExists('community_projects');
    }
};
