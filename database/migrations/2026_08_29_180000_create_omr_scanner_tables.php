<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('item_count');
            $table->unsignedTinyInteger('choice_count')->default(4);
            $table->json('answer_key');
            $table->timestamps();
        });

        Schema::create('omr_scan_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('omr_sheet_id')->constrained('omr_sheets')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->restrictOnDelete();
            $table->json('answers');
            $table->unsignedTinyInteger('correct_count');
            $table->unsignedTinyInteger('blank_count')->default(0);
            $table->decimal('score', 8, 2);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['omr_sheet_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_scan_results');
        Schema::dropIfExists('omr_sheets');
    }
};
