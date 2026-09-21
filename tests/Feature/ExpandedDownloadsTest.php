<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpandedDownloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_download_operational_directories_and_new_class_sheets(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        foreach ([
            '/admin/users/export',
            '/admin/students/export',
            '/admin/components/export',
            '/admin/sections/export',
            '/admin/system-logs/export',
            '/admin/reports/attendance_sheet/export',
            '/admin/reports/grade_sheet/export',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertDownload();
        }
    }

    public function test_student_can_download_personal_reports_and_earned_certificate(): void
    {
        [$student] = $this->studentWithCompletedRequirements();

        foreach (['grades', 'attendance', 'assessments', 'certificate'] as $type) {
            $response = $this->actingAs($student)->get('/student/reports/download/'.$type);
            $response->assertOk()->assertDownload();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
        }
    }

    public function test_incomplete_student_cannot_download_a_completion_certificate(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/student/reports/download/certificate')->assertStatus(422);
    }

    public function test_published_announcement_attachment_is_downloadable_only_by_its_audience_or_manager(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $file = UploadedFile::fake()->create('orientation-guide.pdf', 100, 'application/pdf');

        $this->actingAs($admin)->post('/nstp-admin/announcements', [
            'title' => 'Student orientation',
            'body' => 'Please review the attached guide.',
            'audience' => 'students',
            'component_id' => null,
            'status' => 'published',
            'expires_at' => null,
            'attachment' => $file,
        ])->assertRedirect();

        $announcement = Announcement::firstOrFail();
        Storage::disk('local')->assertExists($announcement->attachment_path);
        $this->actingAs($student)->get('/announcements/'.$announcement->id.'/attachment')
            ->assertOk()->assertDownload('orientation-guide.pdf');
        $this->actingAs($admin)->get('/announcements/'.$announcement->id.'/attachment')
            ->assertOk()->assertDownload('orientation-guide.pdf');
        $this->actingAs($facilitator)->get('/announcements/'.$announcement->id.'/attachment')->assertForbidden();
    }

    /** @return array{User, NstpSection} */
    private function studentWithCompletedRequirements(): array
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Completed Student']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Orientation', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);
        foreach (['activity', 'project', 'exam', 'quiz'] as $type) {
            $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => str($type)->headline().' Requirement', 'type' => $type, 'max_score' => 100, 'weight' => 25, 'status' => 'published', 'published_at' => now()]);
            AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Complete', 'submitted_at' => now(), 'score' => 90, 'graded_by' => $facilitator->id, 'graded_at' => now()]);
        }

        return [$student, $section];
    }
}
