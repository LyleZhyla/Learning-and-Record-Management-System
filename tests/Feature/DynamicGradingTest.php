<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_weights_are_transmuted_to_the_one_to_five_scale(): void
    {
        [$facilitator, $student, $section] = $this->records();
        $types = ['activity', 'project', 'exam', 'quiz'];

        foreach ($types as $type) {
            $assessment = Assessment::create([
                'section_id' => $section->id, 'created_by' => $facilitator->id,
                'title' => ucfirst($type), 'type' => $type, 'max_score' => 100,
                'weight' => 25, 'status' => 'published', 'published_at' => now(),
            ]);
            AssessmentSubmission::create([
                'assessment_id' => $assessment->id, 'student_id' => $student->id,
                'submitted_at' => now(), 'score' => 75, 'graded_by' => $facilitator->id, 'graded_at' => now(),
            ]);
        }

        $summary = app(GradeService::class)->summary($student, $section->id);
        $this->assertSame(75.0, $summary['percentage']);
        $this->assertSame(3.0, $summary['grade']);

        AssessmentSubmission::where('student_id', $student->id)->update(['score' => 100]);
        $this->assertSame(1.0, app(GradeService::class)->summary($student, $section->id)['grade']);

        AssessmentSubmission::where('student_id', $student->id)->update(['score' => 74]);
        $this->assertSame(5.0, app(GradeService::class)->summary($student, $section->id)['grade']);
    }

    public function test_facilitator_can_edit_a_score_from_the_gradebook(): void
    {
        [$facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Quizzes')->firstOrFail();
        $assessment = Assessment::create([
            'section_id' => $section->id, 'grading_category_id' => $category->id,
            'created_by' => $facilitator->id, 'title' => 'Quiz 1', 'type' => 'quiz',
            'max_score' => 50, 'weight' => 20, 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs($facilitator)->putJson('/facilitator/grades/'.$section->id.'/scores', [
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 45,
        ])->assertOk()->assertJsonPath('percentage', 18)->assertJsonPath('grade', 5);

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 45,
        ]);

        $this->actingAs($facilitator)->get('/facilitator/grades?section='.$section->id)
            ->assertOk()
            ->assertSee('Dela Cruz, Juan S.')
            ->assertSee('2026000001')
            ->assertDontSee($student->email);
    }

    public function test_facilitator_cannot_change_the_grading_configuration(): void
    {
        [$facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->firstOrFail();
        $assessment = Assessment::create([
            'section_id' => $section->id, 'grading_category_id' => $category->id,
            'created_by' => $facilitator->id, 'title' => 'Locked Item', 'type' => 'activity',
            'max_score' => 50, 'weight' => 20, 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs($facilitator)->get('/facilitator/grades')
            ->assertOk()
            ->assertSee('Enter scores for the students assigned to your section')
            ->assertDontSee('Grading setup')
            ->assertDontSee('Score items');

        $this->actingAs($facilitator)->put('/facilitator/grades/'.$section->id.'/structure')->assertForbidden();
        $this->actingAs($facilitator)->post('/facilitator/grades/'.$section->id.'/items')->assertForbidden();
        $this->actingAs($facilitator)->put('/facilitator/grades/items/'.$assessment->id)->assertForbidden();
        $this->actingAs($facilitator)->delete('/facilitator/grades/items/'.$assessment->id)->assertForbidden();
        $this->actingAs($facilitator)->delete('/facilitator/grades/categories/'.$category->id)->assertForbidden();
    }

    public function test_gradebook_filters_sections_by_component_and_rotc_ms_level(): void
    {
        [$facilitator, $student, $cwtsSection] = $this->records();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $rotcSection = NstpSection::create(['component_id' => $rotc->id, 'facilitator_id' => $facilitator->id, 'code' => 'ROTC-01', 'name' => 'Alpha Company', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $msOneStudent = User::factory()->create(['name' => 'Alpha Cadet', 'role' => 'student', 'status' => 'active']);
        $msThirtyOneStudent = User::factory()->create(['name' => 'Bravo Officer', 'role' => 'student', 'status' => 'active']);

        NstpEnrollment::create(['student_id' => $msOneStudent->id, 'component_id' => $rotc->id, 'section_id' => $rotcSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-1', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $msThirtyOneStudent->id, 'component_id' => $rotc->id, 'section_id' => $rotcSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-31', 'status' => 'enrolled']);

        $this->actingAs($admin)->get('/admin/grades?component='.$rotc->id.'&section='.$rotcSection->id.'&ms_level=MS-1')
            ->assertOk()
            ->assertSee('NSTP grading and class record')
            ->assertSee('data-grade-component', false)
            ->assertSee('data-ms-level', false)
            ->assertSee('Cadet, Alpha')
            ->assertDontSee('Officer, Bravo');

        $this->actingAs($admin)->get('/admin/grades?component='.$cwtsSection->component_id.'&section='.$cwtsSection->id)
            ->assertOk()
            ->assertSee('data-ms-level-field', false)
            ->assertSee('hidden', false)
            ->assertSee('Dela Cruz, Juan S.');
    }

    public function test_student_sees_only_their_own_transparent_grade_breakdown(): void
    {
        [$facilitator, $student, $section] = $this->records();
        $otherStudent = User::factory()->create(['name' => 'Private Other Student', 'role' => 'student', 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $otherStudent->id, 'component_id' => $section->component_id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Quizzes')->firstOrFail();
        $assessment = Assessment::create([
            'section_id' => $section->id, 'grading_category_id' => $category->id,
            'created_by' => $facilitator->id, 'title' => 'Transparency Quiz', 'type' => 'quiz',
            'max_score' => 50, 'weight' => 20, 'status' => 'published', 'published_at' => now(),
        ]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'submitted_at' => now(), 'score' => 40]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $otherStudent->id, 'submitted_at' => now(), 'score' => 17]);

        $this->actingAs($student)->get('/student/grades')
            ->assertOk()
            ->assertSee('Your grade records')
            ->assertSee('40.00 / 50.00')
            ->assertDontSee('Private Other Student')
            ->assertDontSee('17.00 / 50.00');
    }

    public function test_assessment_requires_and_displays_its_grading_sheet_category(): void
    {
        [$facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Requirements')->firstOrFail();

        $this->actingAs($facilitator)->get('/facilitator/assessments/create')
            ->assertOk()
            ->assertSee('Grading sheet category')
            ->assertSee('data-assessment-category', false)
            ->assertSee('data-section="'.$section->id.'"', false);

        $this->actingAs($facilitator)->post('/facilitator/assessments', [
            'section_id' => $section->id,
            'title' => 'Unmapped Project',
            'type' => 'project',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => 0,
        ])->assertSessionHasErrors('grading_category_id');

        $this->actingAs($facilitator)->post('/facilitator/assessments', [
            'section_id' => $section->id,
            'grading_category_id' => $category->id,
            'title' => 'Mapped Project',
            'type' => 'project',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'title' => 'Mapped Project',
            'grading_category_id' => $category->id,
        ]);
    }

    public function test_score_encoded_on_assessment_page_automatically_appears_in_gradebook(): void
    {
        [$facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Quizzes')->firstOrFail();
        $assessment = Assessment::create([
            'section_id' => $section->id,
            'grading_category_id' => $category->id,
            'created_by' => $facilitator->id,
            'title' => 'Automatic Quiz Score',
            'type' => 'quiz',
            'max_score' => 50,
            'weight' => $category->weight,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($facilitator)->get('/facilitator/assessments/'.$assessment->id)
            ->assertOk()
            ->assertSee('Automatic grading-sheet encoding')
            ->assertSee('/facilitator/assessments/'.$assessment->id.'/students/'.$student->id.'/score', false);

        $this->actingAs($facilitator)->put('/facilitator/assessments/'.$assessment->id.'/students/'.$student->id.'/score', [
            'score' => 45,
            'feedback' => 'Good work.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 45,
            'feedback' => 'Good work.',
        ]);
        $summary = app(GradeService::class)->summary($student, $section->id);
        $quizSummary = $summary['categories']->first(fn ($item) => $item['category']->id === $category->id);
        $this->assertSame(45.0, $quizSummary['earned']);
        $this->assertSame(50.0, $quizSummary['maximum']);
        $this->assertSame(18.0, $quizSummary['weighted_score']);
    }

    private function records(): array
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        StudentProfile::create([
            'user_id' => $student->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'province' => 'Tarlac',
            'province_code' => '036900000',
            'city_municipality' => 'Tarlac City',
            'city_municipality_code' => '036916000',
            'barangay' => 'San Vicente',
            'barangay_code' => '036916001',
            'date_of_birth' => '2005-01-01',
            'birth_province' => 'Tarlac',
            'birth_province_code' => '036900000',
            'birth_city_municipality' => 'Tarlac City',
            'birth_city_municipality_code' => '036916000',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09171234567',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09179876543',
            'student_number' => '2026000001',
            'college' => 'College of Engineering',
            'course' => 'BS Agricultural Engineering',
            'year_section' => '1-A',
        ]);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        return [$facilitator, $student, $section];
    }
}
