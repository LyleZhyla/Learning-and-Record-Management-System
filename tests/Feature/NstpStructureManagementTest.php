<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
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

    public function test_legacy_component_page_redirects_to_sectioning_with_all_three_components(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/nstp-admin/components')
            ->assertRedirect('/nstp-admin/sections');

        $this->actingAs($admin)
            ->get('/nstp-admin/sections')
            ->assertOk()
            ->assertSeeTextInOrder(['CWTS', 'LTS', 'ROTC']);
    }

    public function test_super_admin_can_manage_the_same_nstp_structure_records(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/components')
            ->assertRedirect('/admin/sections');

        $this->actingAs($admin)
            ->get('/admin/sections')
            ->assertOk()
            ->assertSee('Sections & Student Sectioning')
            ->assertSee('Manage sections and student assignments')
            ->assertSee('Automatic sectioning')
            ->assertSee('Run automatic sectioning')
            ->assertSee('Component enrollment')
            ->assertSee('NSTP component configuration')
            ->assertSeeTextInOrder(['CWTS', 'LTS', 'ROTC'])
            ->assertSee('Default capacity')
            ->assertSee('How default capacity works')
            ->assertSee('Sectioning');

        $this->actingAs($admin)->get('/admin/sectioning')
            ->assertOk()->assertSee('Sections & Student Sectioning');
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

        $this->actingAs($admin)->post('/nstp-admin/sectioning/enroll', [
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'student_ids' => [$student->id],
        ])->assertSessionHasNoErrors();

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
}
