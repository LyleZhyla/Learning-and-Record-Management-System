<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_learning_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('nstp_enrollment_id')->constrained('nstp_enrollments')->cascadeOnDelete();
            $table->json('preferences');
            $table->json('guidance');
            $table->timestamps();

            $table->index(['student_id', 'nstp_enrollment_id', 'created_at'], 'ai_recommendations_student_enrollment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learning_recommendations');
    }
};
