<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_categories', function (Blueprint $table): void {
            $table->string('assessment_type', 30)->nullable()->after('name');
        });

        Schema::create('component_assessment_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->unique()->constrained('nstp_components')->cascadeOnDelete();
            $table->json('allowed_types');
            $table->string('default_type', 30)->default('activity');
            $table->decimal('default_max_score', 8, 2)->default(100);
            $table->json('rubric_required_types')->nullable();
            $table->decimal('passing_percentage', 5, 2)->default(75);
            $table->decimal('highest_grade', 3, 2)->default(1);
            $table->decimal('passing_grade', 3, 2)->default(3);
            $table->decimal('failing_grade', 3, 2)->default(5);
            $table->json('category_templates');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('grading_categories')->orderBy('id')->get()->each(function (object $category): void {
            $type = match (strtolower((string) $category->name)) {
                'quizzes', 'quiz' => 'quiz',
                'term test', 'exam', 'exams' => 'exam',
                'requirements', 'requirement' => 'project',
                default => 'activity',
            };
            DB::table('grading_categories')->where('id', $category->id)->update(['assessment_type' => $type]);
        });

        foreach (DB::table('nstp_components')->get(['id', 'code']) as $component) {
            $definition = config('component_assessment_profiles.profiles.'.$component->code)
                ?? config('component_assessment_profiles.default');
            DB::table('component_assessment_settings')->insert([
                'component_id' => $component->id,
                'allowed_types' => json_encode($definition['allowed_types'], JSON_THROW_ON_ERROR),
                'default_type' => $definition['default_type'],
                'default_max_score' => $definition['default_max_score'],
                'rubric_required_types' => json_encode($definition['rubric_required_types'], JSON_THROW_ON_ERROR),
                'passing_percentage' => $definition['passing_percentage'],
                'highest_grade' => $definition['highest_grade'],
                'passing_grade' => $definition['passing_grade'],
                'failing_grade' => $definition['failing_grade'],
                'category_templates' => json_encode($definition['categories'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('component_assessment_settings');
        Schema::table('grading_categories', function (Blueprint $table): void {
            $table->dropColumn('assessment_type');
        });
    }
};
