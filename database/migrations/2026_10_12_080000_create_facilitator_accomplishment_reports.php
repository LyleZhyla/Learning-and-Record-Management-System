<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilitator_accomplishment_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facilitator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('component_id')->constrained('nstp_components')->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->date('report_month');
            $table->text('accomplishment_summary');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->dateTime('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['facilitator_id', 'component_id', 'academic_year', 'semester', 'report_month'],
                'facilitator_monthly_accomplishment_unique'
            );
            $table->index(['report_month', 'component_id'], 'accomplishment_month_component_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilitator_accomplishment_reports');
    }
};
