<?php

namespace Tests\Feature;

use App\Models\LearningMaterial;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentMaterialsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_materials_page_presents_file_and_link_details_for_the_current_class(): void
    {
        [$student, $publisher, $component, $section] = $this->enrolledStudent();

        LearningMaterial::create([
            'component_id' => $component->id,
            'section_id' => $section->id,
            'created_by' => $publisher->id,
            'title' => 'Community Mapping Workbook',
            'description' => 'Use this workbook during the community mapping activity.',
            'file_path' => 'learning-materials/community-mapping.pdf',
            'original_filename' => 'community-mapping.pdf',
            'status' => 'published',
            'published_at' => now(),
        ]);
        LearningMaterial::create([
            'component_id' => $component->id,
            'created_by' => $publisher->id,
            'title' => 'NSTP Reference Portal',
            'external_url' => 'https://example.test/nstp-reference',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs($student)
            ->get('/student/materials')
            ->assertOk()
            ->assertSee('Student resource library')
            ->assertSee('CWTS · CWTS-01')
            ->assertSee('2 materials')
            ->assertSee('Community Mapping Workbook')
            ->assertSee('community-mapping.pdf')
            ->assertSee('PDF')
            ->assertSee('Download')
            ->assertSee('NSTP Reference Portal')
            ->assertSee('External resource')
            ->assertSee('Open link');
    }

    public function test_student_only_sees_published_materials_for_their_component_and_section(): void
    {
        [$student, $publisher, $component, $section] = $this->enrolledStudent();
        $otherSection = NstpSection::create([
            'component_id' => $component->id,
            'code' => 'CWTS-02',
            'name' => 'Section 2',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);

        foreach ([
            ['title' => 'Visible Section Material', 'section_id' => $section->id, 'status' => 'published'],
            ['title' => 'Visible Component Material', 'section_id' => null, 'status' => 'published'],
            ['title' => 'Hidden Other Section Material', 'section_id' => $otherSection->id, 'status' => 'published'],
            ['title' => 'Hidden Draft Material', 'section_id' => $section->id, 'status' => 'draft'],
        ] as $attributes) {
            LearningMaterial::create($attributes + [
                'component_id' => $component->id,
                'created_by' => $publisher->id,
                'external_url' => 'https://example.test/resource',
                'published_at' => now(),
            ]);
        }

        $this->actingAs($student)
            ->get('/student/materials')
            ->assertOk()
            ->assertSee('Visible Section Material')
            ->assertSee('Visible Component Material')
            ->assertDontSee('Hidden Other Section Material')
            ->assertDontSee('Hidden Draft Material');
    }

    public function test_student_without_active_enrollment_sees_an_actionable_empty_state(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)
            ->get('/student/materials')
            ->assertOk()
            ->assertSee('No active enrollment')
            ->assertSee('Complete your enrollment first')
            ->assertSee('Go to NSTP Selection');
    }

    /** @return array{User, User, NstpComponent, NstpSection} */
    private function enrolledStudent(): array
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $publisher = User::factory()->create(['role' => 'facilitator', 'status' => 'active', 'name' => 'Alex Facilitator']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $publisher->id,
            'code' => 'CWTS-01',
            'name' => 'Section 1',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        return [$student, $publisher, $component, $section];
    }
}
