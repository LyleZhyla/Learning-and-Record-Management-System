<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ComponentAssessmentSetting;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentAssessmentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_automatically_receive_their_component_assessment_profile(): void
    {
        $lts = $this->makeComponent('LTS', 'Literacy Training Service');
        $rotc = $this->makeComponent('ROTC', 'Reserve Officers Training Corps');
        $ltsSection = $this->section($lts, 'LTS-01');
        $rotcSection = $this->section($rotc, 'ROTC-01');

        app(GradeService::class)->ensureStructure($ltsSection);
        app(GradeService::class)->ensureStructure($rotcSection);

        $this->assertSame(
            ['Literacy Activities', 'Teaching Projects', 'Knowledge Checks'],
            $ltsSection->gradingCategories()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame(
            ['Drills and Practicum', 'Military Knowledge', 'Written and Practical Exams'],
            $rotcSection->gradingCategories()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(['activity', 'project', 'quiz'], $ltsSection->gradingCategories()->pluck('assessment_type')->all());
        $this->assertEqualsCanonicalizing(['activity', 'quiz', 'exam'], $rotcSection->gradingCategories()->pluck('assessment_type')->all());
        $this->assertSame('75.00', $ltsSection->gradingSetting()->firstOrFail()->passing_percentage);
    }

    public function test_admin_can_configure_a_component_profile_and_apply_it_only_to_empty_sections(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $component = $this->makeComponent('LTS', 'Literacy Training Service');
        $emptySection = $this->section($component, 'LTS-EMPTY');
        $usedSection = $this->section($component, 'LTS-USED');
        $grades = app(GradeService::class);
        $grades->ensureStructure($emptySection);
        $grades->ensureStructure($usedSection);
        $usedCategory = $usedSection->gradingCategories()->where('assessment_type', 'activity')->firstOrFail();
        Assessment::create([
            'section_id' => $usedSection->id,
            'grading_category_id' => $usedCategory->id,
            'created_by' => $admin->id,
            'title' => 'Existing Literacy Activity',
            'type' => 'activity',
            'max_score' => 100,
            'weight' => $usedCategory->weight,
            'status' => 'published',
        ]);

        $this->actingAs($admin)->get(route('nstp_admin.components.edit', $component))
            ->assertOk()
            ->assertSee('LTS-specific assessment settings')
            ->assertSee('Teaching Projects');

        $this->actingAs($admin)->put(route('nstp_admin.components.assessment-profile.update', $component), [
            'allowed_types' => ['activity', 'project', 'quiz'],
            'default_type' => 'project',
            'default_max_score' => 50,
            'rubric_required_types' => ['project'],
            'passing_percentage' => 80,
            'highest_grade' => 1,
            'passing_grade' => 2.75,
            'failing_grade' => 5,
            'categories' => $this->categoryPayload([
                'activity' => ['name' => 'Class Facilitation', 'weight' => 25, 'color' => '#f59e0b'],
                'project' => ['name' => 'Teaching Demonstration', 'weight' => 50, 'color' => '#db2777'],
                'quiz' => ['name' => 'Literacy Quiz', 'weight' => 25, 'color' => '#2563eb'],
                'exam' => ['name' => 'Unused Exam', 'weight' => 25, 'color' => '#16a34a'],
            ]),
            'apply_to_empty_sections' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $profile = ComponentAssessmentSetting::where('component_id', $component->id)->firstOrFail();
        $this->assertSame('project', $profile->default_type);
        $this->assertSame(['project'], $profile->rubric_required_types);
        $this->assertSame(['Class Facilitation', 'Teaching Demonstration', 'Literacy Quiz'], collect($profile->category_templates)->pluck('name')->all());
        $this->assertSame(['Class Facilitation', 'Teaching Demonstration', 'Literacy Quiz'], $emptySection->gradingCategories()->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame('80.00', $emptySection->gradingSetting()->firstOrFail()->passing_percentage);
        $this->assertSame('2.75', $emptySection->gradingSetting()->firstOrFail()->passing_grade);
        $this->assertSame('Literacy Activities', $usedSection->gradingCategories()->orderBy('sort_order')->value('name'));
    }

    public function test_assessment_creation_enforces_component_types_categories_and_rubrics(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = $this->makeComponent('LTS', 'Literacy Training Service');
        $section = $this->section($component, 'LTS-01', $facilitator);
        app(GradeService::class)->ensureStructure($section);
        $activityCategory = $section->gradingCategories()->where('assessment_type', 'activity')->firstOrFail();
        $projectCategory = $section->gradingCategories()->where('assessment_type', 'project')->firstOrFail();

        $this->actingAs($facilitator)->get(route('facilitator.assessments.create'))
            ->assertOk()
            ->assertSee('data-component-assessment-profiles', false)
            ->assertSee('Literacy Activities');

        $this->actingAs($facilitator)->post(route('facilitator.assessments.store'), [
            'section_id' => $section->id,
            'grading_category_id' => $activityCategory->id,
            'title' => 'Disallowed LTS Exam',
            'type' => 'exam',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => '0',
        ])->assertSessionHasErrors('type');

        $this->actingAs($facilitator)->post(route('facilitator.assessments.store'), [
            'section_id' => $section->id,
            'grading_category_id' => $projectCategory->id,
            'title' => 'Teaching Demonstration',
            'type' => 'project',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => '0',
        ])->assertSessionHasErrors('rubric_criteria');

        $this->actingAs($facilitator)->post(route('facilitator.assessments.store'), [
            'section_id' => $section->id,
            'grading_category_id' => $projectCategory->id,
            'title' => 'Teaching Demonstration',
            'type' => 'project',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => '0',
            'rubric_criteria' => [[
                'title' => 'Instructional delivery',
                'description' => 'Uses clear and age-appropriate literacy instruction.',
                'percentage' => 100,
                'score' => 100,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'title' => 'Teaching Demonstration',
            'type' => 'project',
            'grading_category_id' => $projectCategory->id,
        ]);
    }

    private function makeComponent(string $code, string $name): NstpComponent
    {
        return NstpComponent::create([
            'code' => $code,
            'name' => $name,
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
    }

    private function section(NstpComponent $component, string $code, ?User $facilitator = null): NstpSection
    {
        return NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator?->id,
            'code' => $code,
            'name' => $code.' Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
    }

    /** @param array<string, array{name: string, weight: int, color: string}> $categories */
    private function categoryPayload(array $categories): array
    {
        return collect($categories)->map(fn (array $category, string $type): array => [
            ...$category,
            'assessment_type' => $type,
        ])->all();
    }
}
