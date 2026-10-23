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

    public function test_coordinator_dashboard_shows_component_scoped_operational_graphics(): void
    {
        $this->actingAs($this->coordinator)->get('/coordinator/dashboard')
            ->assertOk()
            ->assertSee('Operational checklist')
            ->assertSee('Attendance status')
            ->assertSee('Students per section')
            ->assertSee('Grading completion')
            ->assertSee('Section 1')
            ->assertSee('Attendance rate: 100.0 percent', false)
            ->assertSee('1 graded')
            ->assertSee('0 still awaiting a score');
    }

    public function test_coordinator_dashboard_calendar_only_shows_activities_from_the_assigned_component(): void
    {
        $calendarMonth = now()->addMonth()->startOfMonth();
        $assessment = Assessment::where('title', 'Activity 1')->firstOrFail();
        $assessment->update(['due_at' => $calendarMonth->copy()->addDays(5)->setTime(17, 0)]);

        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $otherComponent = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $otherSection = NstpSection::create([
            'component_id' => $otherComponent->id,
            'facilitator_id' => $otherFacilitator->id,
            'code' => 'ROTC-CALENDAR',
            'name' => 'Hidden Calendar Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        Assessment::create([
            'section_id' => $otherSection->id,
            'created_by' => $otherFacilitator->id,
            'title' => 'Hidden ROTC Calendar Activity',
            'type' => 'activity',
            'max_score' => 100,
            'weight' => 20,
            'due_at' => $calendarMonth->copy()->addDays(7)->setTime(17, 0),
            'status' => 'published',
        ]);

        $this->actingAs($this->coordinator)
            ->get('/coordinator/dashboard?month='.$calendarMonth->format('Y-m'))
            ->assertOk()
            ->assertSee('Activity calendar')
            ->assertSee($calendarMonth->format('F Y'))
            ->assertSee('Activity 1')
            ->assertSee('across your assigned component')
            ->assertDontSee('Hidden ROTC Calendar Activity')
            ->assertDontSee('Hidden Calendar Section');
    }

    public function test_coordinator_can_create_sections_only_for_the_assigned_component(): void
    {
        $assignedComponent = NstpComponent::findOrFail($this->coordinator->nstp_component_id);
        $assignedFacilitator = NstpSection::firstOrFail()->facilitator;
        $otherComponent = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $otherFacilitator = User::factory()->create([
            'role' => 'facilitator',
            'status' => 'active',
            'nstp_component_id' => $otherComponent->id,
        ]);

        $this->actingAs($this->coordinator)->get('/coordinator/sections')
            ->assertOk()
            ->assertSee('+ Create section');
        $this->actingAs($this->coordinator)->get('/coordinator/sections/create')
            ->assertOk()
            ->assertDontSee('Section code')
            ->assertSee($assignedComponent->name)
            ->assertSee($assignedFacilitator->name)
            ->assertDontSee($otherComponent->name)
            ->assertDontSee($otherFacilitator->name);

        $this->actingAs($this->coordinator)->post('/coordinator/sections', [
            'component_id' => $otherComponent->id,
            'facilitator_id' => $assignedFacilitator->id,
            'name' => 'Section 2',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ])->assertRedirect('/coordinator/sections')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_sections', [
            'component_id' => $assignedComponent->id,
            'facilitator_id' => $assignedFacilitator->id,
            'name' => 'Section 2',
        ]);
        $this->assertDatabaseMissing('nstp_sections', [
            'component_id' => $otherComponent->id,
            'name' => 'Section 2',
        ]);

        $this->actingAs($this->coordinator)->post('/coordinator/sections', [
            'facilitator_id' => $otherFacilitator->id,
            'name' => 'Section 3',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ])->assertSessionHasErrors('facilitator_id');
        $this->assertDatabaseMissing('nstp_sections', ['name' => 'Section 3']);

        $this->actingAs($this->coordinator)->post('/coordinator/sections', [
            'name' => 'Section 2',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ])->assertSessionHasErrors('name');
    }

    public function test_nstp_admin_can_still_create_a_section_for_any_active_component(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $component = NstpComponent::firstOrFail();

        $this->actingAs($admin)->post('/nstp-admin/sections', [
            'component_id' => $component->id,
            'name' => 'Admin-created section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 45,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_sections', [
            'component_id' => $component->id,
            'name' => 'Admin-created section',
        ]);
    }

    public function test_coordinator_sidebar_groups_related_pages_together(): void
    {
        $this->actingAs($this->coordinator)->get('/coordinator/dashboard')
            ->assertOk()
            ->assertSeeInOrder([
                'Overview',
                'Dashboard',
                'Program Oversight',
                'Components',
                'Facilitators & Students',
                'Sections',
                'Attendance & Learning',
                'Attendance',
                'Assessment & Grading',
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
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($this->coordinator)->get('/coordinator/reports/students/pdf?component_id='.$otherComponent->id)
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->coordinator)->get('/coordinator/reports/sections/print?component_id='.$otherComponent->id)
            ->assertOk()->assertSee('Section 1')->assertDontSee('Hidden Report Section');
        $this->actingAs($this->coordinator)->get('/coordinator/reports')
            ->assertOk()->assertDontSee('Students by Section');
        $this->actingAs($this->coordinator)->get('/coordinator/reports/students_by_section/export')
            ->assertNotFound();
    }
}
