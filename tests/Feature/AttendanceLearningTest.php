<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLearningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $facilitator;

    private User $student;

    private NstpSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $this->facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $this->section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $this->facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $this->student->id, 'component_id' => $component->id, 'section_id' => $this->section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
    }

    public function test_student_account_receives_a_unique_permanent_qr_code(): void
    {
        $secondStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->assertNotEmpty($this->student->fresh()->student_qr_token);
        $this->assertNotEmpty($secondStudent->student_qr_token);
        $this->assertNotSame($this->student->fresh()->student_qr_token, $secondStudent->student_qr_token);
        $this->actingAs($this->student)->get('/student/attendance')->assertOk()->assertSee('Your permanent attendance QR');
    }

    public function test_facilitator_scans_student_qr_to_record_attendance(): void
    {
        $session = AttendanceSession::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'QR Session', 'starts_at' => now()->subMinute(), 'late_after' => now()->addMinute(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        $payload = $this->student->fresh()->studentQrPayload();

        $this->actingAs($this->facilitator)
            ->postJson('/facilitator/attendance/'.$session->id.'/scan', ['qr_code' => $payload])
            ->assertOk()
            ->assertJsonPath('status', 'present')
            ->assertJsonPath('student_id', $this->student->id)
            ->assertJsonPath('status_label', 'Present');
        $this->actingAs($this->facilitator)
            ->postJson('/facilitator/attendance/'.$session->id.'/scan', ['qr_code' => $payload])
            ->assertOk()
            ->assertJsonPath('recorded', false);

        $this->assertDatabaseHas('attendance_records', ['attendance_session_id' => $session->id, 'student_id' => $this->student->id, 'status' => 'present', 'source' => 'qr', 'recorded_by' => $this->facilitator->id]);
        $this->assertSame(1, AttendanceRecord::where('attendance_session_id', $session->id)->where('student_id', $this->student->id)->count());

        $this->actingAs($this->facilitator)->get('/facilitator/attendance/'.$session->id)
            ->assertOk()
            ->assertSee('data-attendance-student="'.$this->student->id.'"', false)
            ->assertSee('data-attendance-record-count="1"', false);
    }

    public function test_facilitator_coordinator_and_nstp_admin_have_camera_scanner_access(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $this->section->component_id]);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $session = AttendanceSession::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'QR Session', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => '', 'qr_svg' => '', 'status' => 'open']);
        $payload = $this->student->fresh()->studentQrPayload();

        $this->actingAs($this->facilitator)->get('/facilitator/attendance/'.$session->id)
            ->assertOk()
            ->assertSee('data-scanner-video', false)
            ->assertSee('js/vendor/jsQR.js', false)
            ->assertSee('js/attendance-scanner.js', false)
            ->assertDontSee('<th>Source</th>', false);
        $this->actingAs($coordinator)->get('/coordinator/attendance/'.$session->id)
            ->assertOk()
            ->assertSee('data-scanner-video', false)
            ->assertSee('js/vendor/jsQR.js', false);
        $this->actingAs($this->admin)->get('/nstp-admin/attendance/'.$session->id)
            ->assertOk()
            ->assertSee('data-scanner-video', false)
            ->assertSee('js/vendor/jsQR.js', false);
        $this->actingAs($coordinator)->postJson('/coordinator/attendance/'.$session->id.'/scan', ['qr_code' => $payload])->assertOk();
        $this->actingAs($this->admin)->postJson('/nstp-admin/attendance/'.$session->id.'/scan', ['qr_code' => $payload])->assertOk();
        $this->actingAs($superAdmin)->get('/admin/attendance/'.$session->id)
            ->assertOk()
            ->assertSee('<th>Source</th>', false);
        $this->actingAs($superAdmin)->postJson('/admin/attendance/'.$session->id.'/scan', ['qr_code' => $payload])->assertForbidden();
    }

    public function test_published_assessment_score_is_included_in_student_grade(): void
    {
        $assessment = Assessment::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'Activity 1', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $this->student->id, 'answer_text' => 'My response', 'submitted_at' => now(), 'score' => 90, 'graded_by' => $this->facilitator->id, 'graded_at' => now()]);
        $this->actingAs($this->student)->get('/student/grades')->assertOk()->assertSee('90.00%');
    }

    public function test_student_sidebar_shows_the_number_of_unsubmitted_assessments(): void
    {
        $assessments = collect([
            Assessment::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'Pending Activity 1', 'type' => 'activity', 'max_score' => 20, 'weight' => 10, 'status' => 'published', 'published_at' => now()]),
            Assessment::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'Pending Activity 2', 'type' => 'activity', 'max_score' => 20, 'weight' => 10, 'status' => 'published', 'published_at' => now()]),
            Assessment::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'Completed Activity', 'type' => 'activity', 'max_score' => 20, 'weight' => 10, 'status' => 'published', 'published_at' => now()]),
        ]);
        AssessmentSubmission::create(['assessment_id' => $assessments[2]->id, 'student_id' => $this->student->id, 'answer_text' => 'Done', 'submitted_at' => now()]);

        $this->actingAs($this->student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('data-pending-assessment-count="2"', false)
            ->assertSee('2 pending assessments');

        $this->actingAs($this->student)->post('/student/assessments/'.$assessments[0]->id.'/submit', [
            'answer_text' => 'Submitted now',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->student)->get('/student/assessments')
            ->assertOk()
            ->assertSee('data-pending-assessment-count="1"', false);
    }

    public function test_student_can_open_classroom_style_assessment_and_keep_attachment_when_resubmitting_text(): void
    {
        $assessment = Assessment::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'Community Reflection', 'type' => 'activity', 'instructions' => 'Upload your reflection.', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $this->student->id, 'answer_text' => 'First response', 'file_path' => 'assessment-submissions/reflection.pdf', 'original_filename' => 'reflection.pdf', 'submitted_at' => now()]);

        $this->actingAs($this->student)
            ->get('/student/assessments/'.$assessment->id)
            ->assertOk()
            ->assertSee('Your work')
            ->assertSee('reflection.pdf');

        $this->actingAs($this->student)
            ->post('/student/assessments/'.$assessment->id.'/submit', ['answer_text' => 'Updated response'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id,
            'student_id' => $this->student->id,
            'answer_text' => 'Updated response',
            'file_path' => 'assessment-submissions/reflection.pdf',
            'original_filename' => 'reflection.pdf',
        ]);
    }

    public function test_facilitator_cannot_manage_another_facilitators_section(): void
    {
        $other = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->actingAs($other)->post('/facilitator/attendance', ['section_id' => $this->section->id, 'title' => 'Unauthorized', 'starts_at' => now(), 'ends_at' => now()->addHour()])->assertForbidden();
    }

    public function test_facilitator_only_sees_students_assigned_to_their_sections(): void
    {
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $otherStudent = User::factory()->create(['name' => 'Hidden Student', 'role' => 'student', 'status' => 'active']);
        $otherSection = NstpSection::create([
            'component_id' => $this->section->component_id,
            'facilitator_id' => $otherFacilitator->id,
            'code' => 'CWTS-02',
            'name' => 'Other Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create(['student_id' => $otherStudent->id, 'component_id' => $otherSection->component_id, 'section_id' => $otherSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        $this->actingAs($this->facilitator)->get('/facilitator/students')
            ->assertOk()
            ->assertSee($this->student->name)
            ->assertDontSee($otherStudent->name)
            ->assertDontSee('CWTS-02');
        $this->actingAs($this->facilitator)->get('/facilitator/students/'.$this->student->id)
            ->assertOk()
            ->assertSee($this->section->code);
        $this->actingAs($this->facilitator)->get('/facilitator/students/'.$otherStudent->id)->assertNotFound();
    }

    public function test_facilitator_reports_are_limited_to_assigned_sections(): void
    {
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $hiddenStudent = User::factory()->create(['name' => 'Hidden Report Student', 'role' => 'student', 'status' => 'active']);
        $otherSection = NstpSection::create([
            'component_id' => $this->section->component_id,
            'facilitator_id' => $otherFacilitator->id,
            'code' => 'CWTS-HIDDEN',
            'name' => 'Hidden Report Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create(['student_id' => $hiddenStudent->id, 'component_id' => $otherSection->component_id, 'section_id' => $otherSection->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $hiddenSession = AttendanceSession::create(['section_id' => $otherSection->id, 'created_by' => $otherFacilitator->id, 'title' => 'Hidden Attendance Session', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => '', 'qr_svg' => '', 'status' => 'open']);
        AttendanceRecord::create(['attendance_session_id' => $hiddenSession->id, 'student_id' => $hiddenStudent->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);

        $this->actingAs($this->facilitator)->get('/facilitator/reports')
            ->assertOk()
            ->assertSee('My section reports')
            ->assertSee('Reports Center')
            ->assertSee($this->student->name)
            ->assertDontSee($hiddenStudent->name)
            ->assertDontSee($otherSection->code);

        foreach (['students', 'attendance', 'grades', 'sections'] as $type) {
            $this->actingAs($this->facilitator)->get('/facilitator/reports?type='.$type.'&section_id='.$otherSection->id)
                ->assertOk()
                ->assertDontSee($hiddenStudent->name)
                ->assertDontSee('Hidden Attendance Session')
                ->assertDontSee($otherSection->code);
        }

        $this->actingAs($this->facilitator)->get('/facilitator/reports/students/export?section_id='.$otherSection->id)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->facilitator)->get('/facilitator/reports/sections/print?section_id='.$otherSection->id)
            ->assertOk()
            ->assertSee('/facilitator/reports', false)
            ->assertDontSee($otherSection->code);
    }

    public function test_each_authorized_portal_can_open_its_attendance_and_learning_pages(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        foreach ([
            [$superAdmin, '/admin/attendance', '/admin/materials/create', '/admin/assessments', '/admin/grades'],
            [$this->admin, '/nstp-admin/attendance', '/nstp-admin/materials/create', '/nstp-admin/assessments', '/nstp-admin/grades'],
            [$this->facilitator, '/facilitator/students', '/facilitator/attendance', '/facilitator/materials/create', '/facilitator/assessments', '/facilitator/grades', '/facilitator/reports'],
            [$this->student, '/student/attendance', '/student/materials', '/student/assessments', '/student/grades'],
        ] as $portal) {
            $user = array_shift($portal);
            $paths = $portal;
            foreach ($paths as $path) {
                $this->actingAs($user)->get($path)->assertOk();
            }
        }
    }
}
