<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ched_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number', 40)->nullable()->unique();
            $table->string('academic_year', 9);
            $table->string('semester', 20);
            $table->unsignedInteger('student_count')->default(0);
            $table->string('status', 40)->default('draft')->index();
            $table->string('submission_email')->nullable();
            $table->string('ched_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('serials_released_at')->nullable();
            $table->timestamp('last_workbook_downloaded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['academic_year', 'semester']);
        });

        Schema::create('ched_application_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ched_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ched_application_status_histories');
        Schema::dropIfExists('ched_applications');
    }
};
