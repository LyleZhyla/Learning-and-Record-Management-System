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

class AfpRotcSemestralReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_afp_rotc_workbook_includes_passed_failed_and_incomplete_cadets(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'is_active' => true]);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $rotcSection = $this->section($rotc, $facilitator, 'ROTC-01');
        $cwtsSection = $this->section($cwts, $facilitator, 'CWTS-01');

        $passed = $this->student('Passed Cadet', 'Alpha', 'Passed', 'Male', '2026000101');
        $failed = $this->student('Failed Cadet', 'Bravo', 'Failed', 'Male', '2026000102');
        $incomplete = $this->student('Incomplete Cadet', 'Charlie', 'Incomplete', 'Female', '2026000103');
        $nonRotc = $this->student('CWTS Student', 'Delta', 'CWTS', 'Female', '2026000104');

        foreach ([
            [$passed, $rotc, $rotcSection, 'MS-1'],
            [$failed, $rotc, $rotcSection, 'MS-1'],
            [$incomplete, $rotc, $rotcSection, 'MS-1'],
            [$nonRotc, $cwts, $cwtsSection, null],
        ] as [$student, $component, $section, $category]) {
            NstpEnrollment::create([
                'student_id' => $student->id,
                'component_id' => $component->id,
                'section_id' => $section->id,
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'rotc_category' => $category,
                'status' => 'enrolled',
            ]);
        }

        $this->gradeSection($rotcSection, $facilitator, [
            $passed->id => [90, 90, 90, 90],
            $failed->id => [50, 50, 50, 50],
            $incomplete->id => [85, 85, null, null],
        ]);
        $this->gradeSection($cwtsSection, $facilitator, [$nonRotc->id => [95, 95, 95, 95]]);

        $page = $this->actingAs($admin)->get('/admin/reports?type=afp_rotc_semestral&academic_year=2026-2027&semester=first');
        $page->assertOk()
            ->assertSee('AFP ROTC Semestral Report')
            ->assertSee('ALPHA, PASSED')
            ->assertSee('BRAVO, FAILED')
            ->assertSee('CHARLIE, INCOMPLETE')
            ->assertDontSee('CWTS Student')
            ->assertSee('All enrolled ROTC cadets are included, including failed, incomplete, and ungraded records.')
            ->assertSee('AFP ROTC Excel workbook')
            ->assertDontSee('Choose data to include');

        $response = $this->actingAs($admin)->get('/admin/reports/afp_rotc_semestral/export?academic_year=2026-2027&semester=first');
        $response->assertOk()->assertDownload();

        $temporaryFile = tempnam(sys_get_temp_dir(), 'afp-rotc-report-');
        file_put_contents($temporaryFile, $response->streamedContent());
        $workbook = IOFactory::load($temporaryFile);

        $this->assertSame(['COG', 'ROG'], $workbook->getSheetNames());
        $cog = $workbook->getSheetByName('COG');
        $rog = $workbook->getSheetByName('ROG');
        $this->assertSame('(First Semester, SY 2026-2027)', $cog->getCell('A8')->getValue());
        $this->assertSame('MS-1 MALE CADETS', $cog->getCell('A10')->getValue());
        $this->assertSame('ALPHA, PASSED', $cog->getCell('B12')->getValue());
        $this->assertSame('PASSED', $cog->getCell('N12')->getValue());
        $this->assertSame('BRAVO, FAILED', $cog->getCell('B13')->getValue());
        $this->assertSame('FAILED', $cog->getCell('N13')->getValue());
        $this->assertSame('MS-1 FEMALE CADETS', $cog->getCell('A15')->getValue());
        $this->assertSame('CHARLIE, INCOMPLETE', $cog->getCell('B17')->getValue());
        $this->assertSame('IN PROGRESS', $cog->getCell('N17')->getValue());
        $this->assertSame('ALPHA, PASSED', $rog->getCell('B13')->getValue());
        $this->assertSame('FAILED', $rog->getCell('F14')->getValue());
        $this->assertSame('IN PROGRESS', $rog->getCell('F18')->getValue());

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

    private function student(string $name, string $lastName, string $firstName, string $sex, string $studentNumber): User
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
            'course' => 'BSEd',
            'major' => null,
            'year_section' => '1A',
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
