<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('step_key', 120);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'subject_type', 'subject_id', 'step_key'], 'workflow_step_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_completions');
    }
};
