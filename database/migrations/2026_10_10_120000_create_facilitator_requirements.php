<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilitator_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('component_id')->nullable()->constrained('nstp_components')->nullOnDelete();
            $table->json('accepted_extensions');
            $table->unsignedInteger('max_size_kb')->default(5120);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('facilitator_requirement_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facilitator_requirement_id');
            $table->foreign('facilitator_requirement_id', 'facilitator_submission_requirement_fk')
                ->references('id')->on('facilitator_requirements')->cascadeOnDelete();
            $table->foreignId('facilitator_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('facilitator_notes')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();
            $table->unique(['facilitator_requirement_id', 'facilitator_id'], 'facilitator_requirement_submission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilitator_requirement_submissions');
        Schema::dropIfExists('facilitator_requirements');
    }
};
