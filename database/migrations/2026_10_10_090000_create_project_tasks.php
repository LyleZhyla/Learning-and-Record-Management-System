<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->text('submission_notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_original_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
