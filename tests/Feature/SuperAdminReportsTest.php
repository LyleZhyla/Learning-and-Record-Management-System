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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SuperAdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Demo Student']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Week 1', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Activity 1', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Response', 'submitted_at' => now(), 'score' => 90, 'graded_by' => $facilitator->id, 'graded_at' => now()]);
    }

    public function test_super_admin_can_generate_all_report_types(): void
    {
        foreach (['students' => 'Demo Student', 'attendance' => 'Demo Student', 'grades' => 'Demo Student', 'sections' => 'CWTS-01'] as $type => $expected) {
            $this->actingAs($this->superAdmin)->get('/admin/reports?type='.$type.'&academic_year=2026-2027')
                ->assertOk()->assertSee($expected);
        }
    }

    public function test_report_page_offers_format_and_save_location_controls(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/reports?type=students')
            ->assertOk()
            ->assertSee('PDF document')
            ->assertSee('Excel workbook')
            ->assertSee('Choose folder &amp; save', false)
            ->assertSee('data-pdf-url="'.url('/admin/reports/students/pdf').'"', false)
            ->assertSee('data-excel-url="'.url('/admin/reports/students/export').'"', false)
            ->assertSee('js/report-download.js', false);
    }

    public function test_super_admin_can_download_excel_and_open_print_view(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/reports/attendance/export');
        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $temporaryFile = tempnam(sys_get_temp_dir(), 'smart-nstp-report-');
        file_put_contents($temporaryFile, $response->streamedContent());
        $workbook = IOFactory::load($temporaryFile);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('Attendance Report', $sheet->getCell('A2')->getValue());
        $this->assertSame('Student', $sheet->getCell('A6')->getValue());
        $this->assertSame('Demo Student', $sheet->getCell('A7')->getValue());
        $this->assertSame('A7', $sheet->getFreezePane());
        $this->assertSame('A6:I7', $sheet->getAutoFilter()->getRange());

        $workbook->disconnectWorksheets();
        unlink($temporaryFile);

        $this->actingAs($this->superAdmin)->get('/admin/reports/grades/print')
            ->assertOk()->assertSee('Print now')->assertSee('90.00%');
    }

    public function test_super_admin_can_download_a_valid_pdf_report(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/reports/attendance/pdf');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }

    public function test_super_admin_can_download_students_segregated_into_section_worksheets(): void
    {
        $component = NstpComponent::where('code', 'CWTS')->firstOrFail();
        $facilitator = User::where('role', 'facilitator')->firstOrFail();
        $secondSection = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-02',
            'name' => 'Section 2',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $secondStudent = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Second Student']);
        NstpEnrollment::create([
            'student_id' => $secondStudent->id,
            'component_id' => $component->id,
            'section_id' => $secondSection->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
        User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Unassigned Student']);

        $this->actingAs($this->superAdmin)->get('/admin/reports?type=students_by_section')
            ->assertOk()
            ->assertSee('Student Masterlist by Section')
            ->assertSee('CWTS - CWTS-01')
            ->assertSee('CWTS - CWTS-02')
            ->assertSee('Unassigned Students');

        $response = $this->actingAs($this->superAdmin)->get('/admin/reports/students_by_section/export');
        $response->assertOk()->assertDownload();
        $temporaryFile = tempnam(sys_get_temp_dir(), 'smart-nstp-section-report-');
        file_put_contents($temporaryFile, $response->streamedContent());
        $workbook = IOFactory::load($temporaryFile);

        $this->assertSame(['CWTS-01', 'CWTS-02', 'Unassigned'], $workbook->getSheetNames());
        $this->assertSame('Demo Student', $workbook->getSheetByName('CWTS-01')->getCell('A7')->getValue());
        $this->assertSame('Second Student', $workbook->getSheetByName('CWTS-02')->getCell('A7')->getValue());
        $this->assertSame('Unassigned Student', $workbook->getSheetByName('Unassigned')->getCell('A7')->getValue());

        $workbook->disconnectWorksheets();
        unlink($temporaryFile);

        $this->actingAs($this->superAdmin)->get('/admin/reports/students_by_section/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->superAdmin)->get('/admin/reports/students_by_section/print')
            ->assertOk()
            ->assertSee('CWTS - CWTS-01')
            ->assertSee('CWTS - CWTS-02')
            ->assertSee('Unassigned Students');
    }

    public function test_non_super_admin_cannot_access_reports(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/reports')->assertForbidden();
    }
}
