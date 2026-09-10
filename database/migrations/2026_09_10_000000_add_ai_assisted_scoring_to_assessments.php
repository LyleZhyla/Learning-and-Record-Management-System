<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->longText('rubric')->nullable()->after('instructions');
        });

        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->decimal('ai_suggested_score', 8, 2)->nullable()->after('feedback');
            $table->text('ai_feedback')->nullable()->after('ai_suggested_score');
            $table->json('ai_breakdown')->nullable()->after('ai_feedback');
            $table->decimal('ai_confidence', 5, 2)->nullable()->after('ai_breakdown');
            $table->string('ai_model', 100)->nullable()->after('ai_confidence');
            $table->dateTime('ai_generated_at')->nullable()->after('ai_model');
            $table->foreignId('ai_approved_by')->nullable()->after('ai_generated_at')->constrained('users')->nullOnDelete();
            $table->dateTime('ai_approved_at')->nullable()->after('ai_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_approved_by');
            $table->dropColumn([
                'ai_suggested_score',
                'ai_feedback',
                'ai_breakdown',
                'ai_confidence',
                'ai_model',
                'ai_generated_at',
                'ai_approved_at',
            ]);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('rubric');
        });
    }
};
