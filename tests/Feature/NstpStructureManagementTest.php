<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\NstpComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NstpStructureManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NstpComponentSeeder::class);
    }

    public function test_component_page_shows_enrollment_analytics_for_all_three_components(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/nstp-admin/components')
            ->assertOk()
            ->assertSee('Enrollees per component')
            ->assertSee('Enrollees per college')
            ->assertSee('Enrollees per course')
            ->assertSee('Enrollees per province')
            ->assertSee('Enrollees according to sex')
            ->assertSeeTextInOrder(['CWTS', 'LTS', 'ROTC']);
    }

    public function test_rotc_analytics_can_be_filtered_by_ms_level(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $rotc = NstpComponent::where('code', 'ROTC')->firstOrFail();
        $msOneStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $msThirtyOneStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $studentWithoutProfile = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $studentWithBlankProfileFields = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->createAnalyticsProfile($msOneStudent, '2026000001', 'College of Education', 'BSEd', 'Bulacan', 'Female');
        $this->createAnalyticsProfile($msThirtyOneStudent, '2026000002', 'College of Engineering', 'BSCE', 'Pampanga', 'Male');
        $this->createAnalyticsProfile($studentWithBlankProfileFields, '2026000003', '', '', '', '');

        NstpEnrollment::create(['student_id' => $msOneStudent->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-1', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $msThirtyOneStudent->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-31', 'status' => 'pending_approval']);
        NstpEnrollment::create(['student_id' => $studentWithoutProfile->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-31', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $studentWithBlankProfileFields->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-31', 'status' => 'enrolled']);

        $this->actingAs($admin)->get('/nstp-admin/components?component='.$rotc->id.'&academic_year=2026-2027&semester=first&ms_level=MS-31')
            ->assertOk()
            ->assertViewHas('selectedEnrollmentCount', 3)
            ->assertViewHas('breakdowns', fn (array $breakdowns) => $breakdowns['college']->contains(fn (array $row) => $row['label'] === 'Not provided' && $row['count'] === 2))
            ->assertSee('ROTC · MS-31')
            ->assertSee('College of Engineering')
            ->assertSee('Not provided')
            ->assertSee('BSCE')
            ->assertSee('Pampanga')
            ->assertSee('Male')
            ->assertDontSee('College of Education')
            ->assertDontSee('BSEd')
            ->assertDontSee('Bulacan')
            ->assertDontSee('Female');
    }

    public function test_all_components_scope_compares_demographics_side_by_side(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $components = NstpComponent::query()->get()->keyBy('code');
        $cwtsStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $ltsStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $rotcStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->createAnalyticsProfile($cwtsStudent, '2026100001', 'College of Engineering', 'BSCE', 'Bulacan', 'Female');
        $this->createAnalyticsProfile($ltsStudent, '2026100002', 'College of Engineering', 'BSIT', 'Pampanga', 'Male');
        $this->createAnalyticsProfile($rotcStudent, '2026100003', 'College of Education', 'BSEd', 'Bulacan', 'Male');

        foreach ([['CWTS', $cwtsStudent], ['LTS', $ltsStudent], ['ROTC', $rotcStudent]] as [$code, $student]) {
            NstpEnrollment::create([
                'student_id' => $student->id,
                'component_id' => $components[$code]->id,
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'shirt_size' => 'M',
                'rotc_category' => $code === 'ROTC' ? 'MS-1' : null,
                'status' => 'enrolled',
            ]);
        }

        $this->actingAs($admin)->get('/nstp-admin/components?component=all&academic_year=2026-2027&semester=first')
            ->assertOk()
            ->assertViewHas('compareAllComponents', true)
            ->assertViewHas('selectedEnrollmentCount', 3)
            ->assertViewHas('comparisonBreakdowns', function (array $breakdowns): bool {
                $engineering = $breakdowns['college']->firstWhere('label', 'College of Engineering');

                return $engineering['total'] === 2
                    && $engineering['counts']['CWTS'] === 1
                    && $engineering['counts']['LTS'] === 1
                    && $engineering['counts']['ROTC'] === 0;
            })
            ->assertSee('All components — Compare')
            ->assertSee('Compare CWTS, LTS, and ROTC within every category.')
            ->assertSee('College of Engineering')
            ->assertSee('College of Education');
    }

    public function test_super_admin_can_manage_the_same_nstp_structure_records(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/components')
            ->assertOk()
            ->assertSee('NSTP component insights')
            ->assertSee('Enrollees per component');

        $this->actingAs($admin)
            ->get('/admin/sections')
            ->assertOk()
            ->assertSee('Automatic sectioning')
            ->assertSee('All component sections')
            ->assertSee('Sections')
            ->assertDontSee('Student assignment')
            ->assertDontSee('Enroll students in CWTS')
            ->assertSee('Run automatic sectioning')
            ->assertDontSee('Choose workspace')
            ->assertDontSee('Component settings (advanced)')
            ->assertDontSee('How default capacity works');

        $this->actingAs($admin)->get('/admin/sectioning')
            ->assertOk()
            ->assertSee('Automatic sectioning')
            ->assertSee('Sections');
    }

    public function test_sectioning_defaults_to_showing_sections_from_all_components(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $cwts = NstpComponent::where('code', 'CWTS')->firstOrFail();
        $lts = NstpComponent::where('code', 'LTS')->firstOrFail();

        NstpSection::create([
            'component_id' => $cwts->id,
            'code' => 'CWTS-DEFAULT',
            'name' => 'Default workspace section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpSection::create([
            'component_id' => $lts->id,
            'code' => 'LTS-HIDDEN',
            'name' => 'Other workspace section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/nstp-admin/sections?academic_year=2026-2027&semester=first')
            ->assertOk()
            ->assertViewHas('componentId', null)
            ->assertViewHas('showAllComponents', true)
            ->assertSee('All component sections')
            ->assertSee('CWTS-DEFAULT')
            ->assertSee('LTS-HIDDEN');
    }

    public function test_sectioning_can_show_sections_from_all_components(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $studentIds = [];

        foreach (['CWTS', 'LTS', 'ROTC'] as $code) {
            $component = NstpComponent::where('code', $code)->firstOrFail();
            $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
            $studentIds[] = $student->id;
            NstpSection::create([
                'component_id' => $component->id,
                'code' => $code.'-ALL',
                'name' => $code.' all-components section',
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'capacity' => 40,
                'status' => 'active',
            ]);
            NstpEnrollment::create([
                'student_id' => $student->id,
                'component_id' => $component->id,
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'status' => 'enrolled',
            ]);
        }

        $this->actingAs($admin)
            ->get('/nstp-admin/sections?component_id=all&academic_year=2026-2027&semester=first')
            ->assertOk()
            ->assertViewHas('componentId', null)
            ->assertViewHas('showAllComponents', true)
            ->assertSee('All components')
            ->assertSee('All component sections')
            ->assertSee('CWTS-ALL')
            ->assertSee('LTS-ALL')
            ->assertSee('ROTC-ALL')
            ->assertSee('Run automatic sectioning for all components');

        $this->actingAs($admin)->post('/nstp-admin/sectioning/automate', [
            'component_id' => 'all',
            'academic_year' => '2026-2027',
            'semester' => 'first',
        ])->assertRedirect('/nstp-admin/sections?component_id=all&academic_year=2026-2027&semester=first');

        foreach ($studentIds as $studentId) {
            $this->assertDatabaseMissing('nstp_enrollments', [
                'student_id' => $studentId,
                'section_id' => null,
            ]);
        }
    }

    public function test_component_edit_from_sectioning_returns_to_the_sectioning_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $component = NstpComponent::where('code', 'CWTS')->firstOrFail();

        $this->actingAs($admin)
            ->get('/nstp-admin/components/'.$component->id.'/edit')
            ->assertOk()
            ->assertSee('Back to sectioning');

        $this->actingAs($admin)->put('/nstp-admin/components/'.$component->id, [
            'name' => $component->name,
            'description' => 'Updated from sectioning.',
            'default_section_capacity' => 45,
            'is_active' => 1,
        ])->assertRedirect('/nstp-admin/sections');

        $this->assertDatabaseHas('nstp_components', [
            'id' => $component->id,
            'default_section_capacity' => 45,
        ]);
    }

    public function test_nstp_admin_can_create_a_section_with_a_facilitator(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::where('code', 'CWTS')->firstOrFail();

        $this->actingAs($admin)->post('/nstp-admin/sections', [
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'CWTS Section 1',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_sections', [
            'code' => 'CWTS-01',
            'facilitator_id' => $facilitator->id,
            'capacity' => 40,
        ]);

        $this->actingAs($admin)->get('/nstp-admin/sections?component_id='.$component->id.'&academic_year=2026-2027&semester=first')
            ->assertOk()->assertSee('CWTS-01')->assertSee('Manage section')->assertSee($facilitator->name);
    }

    public function test_automated_sectioning_creates_sections_and_assigns_students(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::where('code', 'LTS')->firstOrFail();
        $component->update(['default_section_capacity' => 2]);

        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        $this->actingAs($admin)->post('/nstp-admin/sectioning/automate', [
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
        ])->assertSessionHasNoErrors();

        $section = NstpSection::where('component_id', $component->id)->firstOrFail();
        $enrollment = NstpEnrollment::where('student_id', $student->id)->firstOrFail();

        $this->assertSame(2, $section->capacity);
        $this->assertSame($section->id, $enrollment->section_id);
    }

    public function test_nstp_admin_and_super_admin_can_automatically_section_every_component(): void
    {
        $roles = [
            'nstp_admin' => ['prefix' => 'nstp-admin', 'academic_year' => '2027-2028'],
            'super_admin' => ['prefix' => 'admin', 'academic_year' => '2028-2029'],
        ];

        foreach ($roles as $role => $context) {
            $admin = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($admin)
                ->get('/'.$context['prefix'].'/sections')
                ->assertOk()
                ->assertSee('Automatic sectioning')
                ->assertSeeTextInOrder(['CWTS', 'LTS', 'ROTC']);

            foreach (NstpComponent::orderBy('code')->get() as $component) {
                $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
                $enrollment = NstpEnrollment::create([
                    'student_id' => $student->id,
                    'component_id' => $component->id,
                    'academic_year' => $context['academic_year'],
                    'semester' => 'first',
                    'status' => 'enrolled',
                ]);

                $this->actingAs($admin)->post('/'.$context['prefix'].'/sectioning/automate', [
                    'component_id' => $component->id,
                    'academic_year' => $context['academic_year'],
                    'semester' => 'first',
                ])->assertSessionHasNoErrors();

                $this->assertNotNull($enrollment->fresh()->section_id);
            }
        }

        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::firstOrFail();
        $payload = [
            'component_id' => $component->id,
            'academic_year' => '2029-2030',
            'semester' => 'first',
        ];

        $this->actingAs($student)->post('/nstp-admin/sectioning/automate', $payload)->assertForbidden();
        $this->actingAs($student)->post('/admin/sectioning/automate', $payload)->assertForbidden();
    }

    public function test_section_capacity_cannot_be_lower_than_current_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $secondStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::where('code', 'ROTC')->firstOrFail();
        $section = NstpSection::create([
            'component_id' => $component->id,
            'code' => 'ROTC-01',
            'name' => 'ROTC Section 1',
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
        NstpEnrollment::create([
            'student_id' => $secondStudent->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        $this->actingAs($admin)->put("/nstp-admin/sections/{$section->id}", [
            'component_id' => $component->id,
            'facilitator_id' => null,
            'code' => $section->code,
            'name' => $section->name,
            'academic_year' => $section->academic_year,
            'semester' => $section->semester,
            'capacity' => 1,
            'status' => 'active',
        ])->assertSessionHasErrors('capacity');
    }

    private function createAnalyticsProfile(User $user, string $studentNumber, string $college, string $course, string $province, string $sex): void
    {
        StudentProfile::create([
            'user_id' => $user->id, 'last_name' => 'Student', 'first_name' => 'Analytics',
            'province' => $province, 'province_code' => '000000000', 'city_municipality' => 'Sample City',
            'city_municipality_code' => '000000000', 'barangay' => 'Sample Barangay', 'barangay_code' => '000000000',
            'date_of_birth' => '2006-01-01', 'birth_province' => $province, 'birth_province_code' => '000000000',
            'birth_city_municipality' => 'Sample City', 'birth_city_municipality_code' => '000000000',
            'religion' => 'Roman Catholic', 'sex' => $sex, 'blood_type' => 'O+', 'contact_number' => '09123456789',
            'emergency_contact_name' => 'Parent', 'emergency_relationship' => 'Parent',
            'emergency_contact_number' => '09987654321', 'emergency_same_address' => true,
            'student_number' => $studentNumber, 'college' => $college, 'course' => $course, 'year_section' => '1A',
        ]);
    }
}
