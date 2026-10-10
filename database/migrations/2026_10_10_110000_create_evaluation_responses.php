<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_responses', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40)->index();
            $table->foreignId('section_id')->nullable()->constrained('nstp_sections')->nullOnDelete();
            $table->foreignId('community_project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_relationship')->nullable();
            $table->json('answers');
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['type', 'section_id', 'evaluator_id', 'subject_user_id'], 'evaluation_response_unique');
            $table->index(['community_project_id', 'type'], 'evaluation_project_type_index');
        });

        Schema::table('community_projects', function (Blueprint $table): void {
            $table->string('feedback_token', 64)->nullable()->unique()->after('implementation_notes');
            $table->boolean('feedback_is_open')->default(false)->after('feedback_token');
        });

        DB::table('community_projects')->whereNull('feedback_token')->orderBy('id')->eachById(function ($project): void {
            DB::table('community_projects')->where('id', $project->id)->update(['feedback_token' => Str::random(48)]);
        });
    }

    public function down(): void
    {
        Schema::table('community_projects', function (Blueprint $table): void {
            $table->dropUnique(['feedback_token']);
            $table->dropColumn(['feedback_token', 'feedback_is_open']);
        });
        Schema::dropIfExists('evaluation_responses');
    }
};
