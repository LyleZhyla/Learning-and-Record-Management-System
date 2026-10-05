<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ChedSemestralReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ched_semestral_workbook_contains_only_completed_passing_cwts_and_lts_students(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $lts = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'is_active' => true]);
        $cwtsSection = $this->section($cwts, $facilitator, 'CWTS-01');
        $ltsSection = $this->section($lts, $facilitator, 'LTS-01');
        $rotcSection = $this->section($rotc, $facilitator, 'ROTC-01');

        $cwtsPasser = $this->student('Ana Passer', 'Passer', 'Ana', 'Female', '2026000001', '4A');
        $ltsPasser = $this->student('Ben Graduate', 'Graduate', 'Ben', 'Male', '2026000002', '3B');
        $failed = $this->student('Cara Failed', 'Failed', 'Cara', 'Female', '2026000003', '2A');
        $incomplete = $this->student('Dan Incomplete', 'Incomplete', 'Dan', 'Male', '2026000004', '2B');
        $rotcPasser = $this->student('Eli Rotc', 'Rotc', 'Eli', 'Male', '2026000005', '1A');

        foreach ([
            [$cwtsPasser, $cwts, $cwtsSection],
            [$failed, $cwts, $cwtsSection],
            [$incomplete, $cwts, $cwtsSection],
            [$ltsPasser, $lts, $ltsSection],
            [$rotcPasser, $rotc, $rotcSection],
        ] as [$student, $component, $section]) {
            NstpEnrollment::create([
                'student_id' => $student->id,
                'component_id' => $component->id,
                'section_id' => $section->id,
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'status' => 'enrolled',
            ]);
        }

        $this->gradeSection($cwtsSection, $facilitator, [
            $cwtsPasser->id => [90, 90, 90, 90],
            $failed->id => [50, 50, 50, 50],
            $incomplete->id => [90, 90, 90, null],
        ]);
        $this->gradeSection($ltsSection, $facilitator, [$ltsPasser->id => [88, 88, 88, 88]]);
        $this->gradeSection($rotcSection, $facilitator, [$rotcPasser->id => [95, 95, 95, 95]]);

        $page = $this->actingAs($admin)->get('/admin/reports?type=ched_semestral&academic_year=2026-2027&semester=first');
        $page->assertOk()
            ->assertSee('CHED Semestral Report')
            ->assertSee('Ana')
            ->assertSee('Ben')
            ->assertDontSee('Cara Failed')
            ->assertDontSee('Dan Incomplete')
            ->assertDontSee('Eli Rotc')
            ->assertSee('Only completed, passing CWTS and LTS students are included.')
            ->assertSee('CHED Excel workbook')
            ->assertDontSee('Choose data to include');

        $response = $this->actingAs($admin)->get('/admin/reports/ched_semestral/export?academic_year=2026-2027&semester=first');
        $response->assertOk()->assertDownload();

        $temporaryFile = tempnam(sys_get_temp_dir(), 'ched-semestral-report-');
        file_put_contents($temporaryFile, $response->streamedContent());
        $workbook = IOFactory::load($temporaryFile);

        $this->assertSame(['Summary', 'NSTP Enrollment List', 'NSTP Graduates'], $workbook->getSheetNames());
        $summary = $workbook->getSheetByName('Summary');
        $this->assertStringContainsString('AY 2026-2027 (First Semester)', $summary->getCell('A2')->getValue());
        $this->assertSame(0, $summary->getCell('D6')->getValue());
        $this->assertSame(1, $summary->getCell('E6')->getValue());
        $this->assertSame(1, $summary->getCell('D7')->getValue());
        $this->assertSame(0, $summary->getCell('E7')->getValue());
        $this->assertSame($summary->getCell('D6')->getValue(), $summary->getCell('D12')->getValue());
        $this->assertSame($summary->getCell('E7')->getValue(), $summary->getCell('E13')->getValue());

        foreach (['NSTP Enrollment List', 'NSTP Graduates'] as $sheetName) {
            $sheet = $workbook->getSheetByName($sheetName);
            $this->assertSame('CWTS', $sheet->getCell('C3')->getValue());
            $this->assertSame('LTS', $sheet->getCell('C4')->getValue());
            $this->assertSame('Passer', $sheet->getCell('F3')->getValue());
            $this->assertSame('Graduate', $sheet->getCell('F4')->getValue());
            $this->assertSame('', (string) $sheet->getCell('E3')->getValue());
            $this->assertSame('Tarlac Agricultural University', $sheet->getCell('O3')->getValue());
            $this->assertSame('SUCs', $sheet->getCell('P3')->getValue());
            $this->assertSame('4', (string) $sheet->getCell('R3')->getValue());
            $this->assertNull($sheet->getCell('A5')->getValue());
        }

        $workbook->disconnectWorksheets();
        unlink($temporaryFile);
    }

    private function section(NstpComponent $component, User $facilitator, string $code): NstpSection
    {
        return NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => $code,
            'name' => $code,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
    }

    private function student(string $name, string $lastName, string $firstName, string $sex, string $studentNumber, string $yearSection): User
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => $name]);
        StudentProfile::create([
            'user_id' => $student->id,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'province' => 'Tarlac',
            'province_code' => '036900000',
            'city_municipality' => 'Camiling',
            'city_municipality_code' => '036903000',
            'barangay' => 'Malacampa',
            'barangay_code' => '036903015',
            'date_of_birth' => '2005-04-15',
            'birth_province' => 'Tarlac',
            'birth_province_code' => '036900000',
            'birth_city_municipality' => 'Camiling',
            'birth_city_municipality_code' => '036903000',
            'religion' => 'Roman Catholic',
            'sex' => $sex,
            'blood_type' => 'O+',
            'contact_number' => '09123456789',
            'emergency_contact_name' => 'Guardian',
            'emergency_relationship' => 'Guardian',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => true,
            'student_number' => $studentNumber,
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education',
            'major' => null,
            'year_section' => $yearSection,
        ]);

        return $student;
    }

    /** @param array<int, array<int, int|null>> $scoresByStudent */
    private function gradeSection(NstpSection $section, User $facilitator, array $scoresByStudent): void
    {
        foreach (['activity', 'project', 'exam', 'quiz'] as $index => $type) {
            $assessment = Assessment::create([
                'section_id' => $section->id,
                'created_by' => $facilitator->id,
                'title' => str($type)->headline().' Requirement',
                'type' => $type,
                'max_score' => 100,
                'weight' => 25,
                'status' => 'published',
                'published_at' => now(),
            ]);

            foreach ($scoresByStudent as $studentId => $scores) {
                if ($scores[$index] === null) {
                    continue;
                }
                AssessmentSubmission::create([
                    'assessment_id' => $assessment->id,
                    'student_id' => $studentId,
                    'answer_text' => 'Complete',
                    'submitted_at' => now(),
                    'score' => $scores[$index],
                    'graded_by' => $facilitator->id,
                    'graded_at' => now(),
                ]);
            }
        }
    }
}
