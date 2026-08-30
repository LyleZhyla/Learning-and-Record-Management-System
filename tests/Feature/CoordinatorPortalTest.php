<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\GradingCategory;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'password' => Hash::make('Secure!Password2026')]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $this->coordinator->update(['nstp_component_id' => $component->id]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Coordinator Demo Attendance', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Activity 1', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Response', 'submitted_at' => now(), 'score' => 90, 'graded_by' => $facilitator->id, 'graded_at' => now()]);
    }

    public function test_coordinator_signs_in_to_own_dashboard(): void
    {
        $this->post('/login', ['email' => $this->coordinator->email, 'password' => 'Secure!Password2026'])
            ->assertRedirect('/coordinator/dashboard');
        $this->assertAuthenticatedAs($this->coordinator);
    }

    public function test_coordinator_can_open_all_read_only_monitoring_pages(): void
    {
        foreach (['/coordinator/dashboard', '/coordinator/components', '/coordinator/accounts', '/coordinator/sections', '/coordinator/attendance', '/coordinator/performance', '/coordinator/reports', '/coordinator/profile'] as $path) {
            $this->actingAs($this->coordinator)->get($path)->assertOk();
        }
    }

    public function test_coordinator_sidebar_groups_related_pages_together(): void
    {
        $this->actingAs($this->coordinator)->get('/coordinator/dashboard')
            ->assertOk()
            ->assertSeeInOrder([
                'Overview',
                'Dashboard',
                'NSTP Operations',
                'Components',
                'Facilitators & Students',
                'Sections & Facilitators',
                'Attendance & Grading',
                'Attendance',
                'Answer Sheet Scanner',
                'Performance & Grades',
                'Grading Setup',
                'Reports',
                'Reports Center',
                'Communication',
                'Announcements',
                'Account',
                'Profile & Security',
            ]);
    }

    public function test_coordinator_can_edit_the_grading_configuration(): void
    {
        $section = NstpSection::firstOrFail();
        $this->actingAs($this->coordinator)->get('/coordinator/grades')
            ->assertOk()
            ->assertSee('Grading setup')
            ->assertSee('Score items');

        $categories = GradingCategory::where('section_id', $section->id)->orderBy('sort_order')->get();
        $payload = $categories->mapWithKeys(fn ($category) => [$category->id => [
            'name' => $category->name === 'Class Standing' ? 'Participation' : $category->name,
            'weight' => $category->weight,
            'color' => $category->color,
        ]])->all();

        $this->actingAs($this->coordinator)->put('/coordinator/grades/'.$section->id.'/structure', [
            'categories' => $payload,
            'passing_percentage' => 75,
            'highest_grade' => 1,
            'passing_grade' => 3,
            'failing_grade' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('grading_categories', [
            'section_id' => $section->id,
            'name' => 'Participation',
        ]);
    }

    public function test_coordinator_cannot_access_management_routes(): void
    {
        $this->actingAs($this->coordinator)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->coordinator)->get('/nstp-admin/sections')->assertForbidden();
        $this->actingAs($this->coordinator)->get('/facilitator/attendance')->assertForbidden();
    }

    public function test_coordinator_only_sees_accounts_and_records_from_assigned_component(): void
    {
        $otherFacilitator = User::factory()->create(['name' => 'Hidden ROTC Facilitator', 'role' => 'facilitator', 'status' => 'active']);
        $otherStudent = User::factory()->create(['name' => 'Hidden ROTC Student', 'role' => 'student', 'status' => 'active']);
        $otherComponent = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $otherSection = NstpSection::create(['component_id' => $otherComponent->id, 'facilitator_id' => $otherFacilitator->id, 'code' => 'ROTC-01', 'name' => 'ROTC Section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $otherStudent->id, 'component_id' => $otherComponent->id, 'section_id' => $otherSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $otherSession = AttendanceSession::create(['section_id' => $otherSection->id, 'created_by' => $otherFacilitator->id, 'title' => 'Hidden ROTC Attendance', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'hidden', 'qr_svg' => '', 'status' => 'open']);

        $this->actingAs($this->coordinator)->get('/coordinator/accounts')
            ->assertOk()
            ->assertDontSee($otherFacilitator->name)
            ->assertDontSee($otherStudent->name);
        $this->actingAs($this->coordinator)->get('/coordinator/sections')->assertDontSee('ROTC-01');
        $this->actingAs($this->coordinator)->get('/coordinator/attendance')->assertDontSee('Hidden ROTC Attendance');
        $this->actingAs($this->coordinator)->get('/coordinator/accounts/'.$otherStudent->id)->assertNotFound();
        $this->actingAs($this->coordinator)->get('/coordinator/attendance/'.$otherSession->id)->assertForbidden();
        $this->actingAs($this->coordinator)->put('/coordinator/grades/'.$otherSection->id.'/structure', [])->assertForbidden();
    }

    public function test_coordinator_reports_are_limited_to_the_assigned_component(): void
    {
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $hiddenStudent = User::factory()->create(['name' => 'Hidden Report Student', 'role' => 'student', 'status' => 'active']);
        $otherComponent = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $otherSection = NstpSection::create(['component_id' => $otherComponent->id, 'facilitator_id' => $otherFacilitator->id, 'code' => 'ROTC-RPT', 'name' => 'Hidden Report Section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $hiddenStudent->id, 'component_id' => $otherComponent->id, 'section_id' => $otherSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        foreach (['students', 'attendance', 'grades', 'sections'] as $type) {
            $this->actingAs($this->coordinator)->get('/coordinator/reports?type='.$type.'&component_id='.$otherComponent->id)
                ->assertOk()
                ->assertSee('CWTS operational reports')
                ->assertDontSee('Hidden Report Student')
                ->assertDontSee('ROTC-RPT');
        }

        $this->actingAs($this->coordinator)->get('/coordinator/reports/students/export?component_id='.$otherComponent->id)
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->coordinator)->get('/coordinator/reports/sections/print?component_id='.$otherComponent->id)
            ->assertOk()->assertSee('CWTS-01')->assertDontSee('ROTC-RPT');
    }
}
