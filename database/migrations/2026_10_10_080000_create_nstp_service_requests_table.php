<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nstp_service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 24)->unique();
            $table->string('request_type', 40);
            $table->string('assistance_type', 40)->nullable();
            $table->string('requester_name', 180);
            $table->string('email', 180);
            $table->string('contact_number', 40);
            $table->string('student_number', 60)->nullable();
            $table->string('program', 180)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->text('purpose');
            $table->string('event_name', 180)->nullable();
            $table->date('event_date')->nullable();
            $table->string('event_location', 255)->nullable();
            $table->unsignedInteger('expected_participants')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 120)->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->text('status_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->dateTime('privacy_accepted_at');
            $table->timestamps();

            $table->index(['request_type', 'status']);
            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nstp_service_requests');
    }
};
