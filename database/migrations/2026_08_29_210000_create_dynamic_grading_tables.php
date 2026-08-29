<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('nstp_sections')->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('weight', 5, 2);
            $table->string('color', 7)->default('#2563eb');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['section_id', 'sort_order']);
        });

        Schema::create('grading_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->unique()->constrained('nstp_sections')->cascadeOnDelete();
            $table->decimal('passing_percentage', 5, 2)->default(75);
            $table->decimal('highest_grade', 3, 2)->default(1);
            $table->decimal('passing_grade', 3, 2)->default(3);
            $table->decimal('failing_grade', 3, 2)->default(5);
            $table->timestamps();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('grading_category_id')->nullable()->after('section_id')->constrained('grading_categories')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0)->after('weight');
        });

        $defaults = [
            ['name' => 'Class Standing', 'weight' => 20, 'color' => '#f59e0b', 'types' => ['activity']],
            ['name' => 'Requirements', 'weight' => 30, 'color' => '#db2777', 'types' => ['project']],
            ['name' => 'Term Test', 'weight' => 30, 'color' => '#16a34a', 'types' => ['exam']],
            ['name' => 'Quizzes', 'weight' => 20, 'color' => '#2563eb', 'types' => ['quiz']],
        ];

        foreach (DB::table('nstp_sections')->pluck('id') as $sectionId) {
            DB::table('grading_settings')->insert([
                'section_id' => $sectionId,
                'passing_percentage' => 75,
                'highest_grade' => 1,
                'passing_grade' => 3,
                'failing_grade' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($defaults as $order => $default) {
                $categoryId = DB::table('grading_categories')->insertGetId([
                    'section_id' => $sectionId,
                    'name' => $default['name'],
                    'weight' => $default['weight'],
                    'color' => $default['color'],
                    'sort_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('assessments')
                    ->where('section_id', $sectionId)
                    ->whereIn('type', $default['types'])
                    ->update(['grading_category_id' => $categoryId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grading_category_id');
            $table->dropColumn('sort_order');
        });
        Schema::dropIfExists('grading_settings');
        Schema::dropIfExists('grading_categories');
    }
};
