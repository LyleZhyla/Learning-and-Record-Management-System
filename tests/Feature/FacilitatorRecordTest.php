<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FacilitatorRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_a_complete_facilitator_record(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Ana Facilitator',
            'email' => 'ana.facilitator@example.test',
            'role' => 'facilitator',
            'status' => 'active',
            'employee_number' => 'FAC-2026-010',
            'department' => 'NSTP Office',
            'designation' => 'NSTP Facilitator',
            'employment_status' => 'full_time',
            'contact_number' => '09171234567',
            'specialization' => 'Disaster preparedness',
            'professional_summary' => 'Handles community preparedness activities.',
        ])->assertSessionHasNoErrors();

        $facilitator = User::where('email', 'ana.facilitator@example.test')->firstOrFail();
        $this->assertDatabaseHas('facilitator_profiles', [
            'user_id' => $facilitator->id,
            'employee_number' => 'FAC-2026-010',
            'department' => 'NSTP Office',
            'contact_number' => '09171234567',
        ]);

        $this->actingAs($admin)->put('/admin/users/'.$facilitator->id, [
            'name' => $facilitator->name,
            'email' => $facilitator->email,
            'role' => 'facilitator',
            'status' => 'active',
            'employee_number' => 'FAC-2026-010',
            'department' => 'College of Arts and Sciences',
            'designation' => 'Senior NSTP Facilitator',
            'employment_status' => 'part_time',
            'contact_number' => '09981234567',
            'specialization' => 'Community health',
            'professional_summary' => 'Leads community health programs.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facilitator_profiles', [
            'user_id' => $facilitator->id,
            'department' => 'College of Arts and Sciences',
            'designation' => 'Senior NSTP Facilitator',
            'employment_status' => 'part_time',
        ]);
    }

    public function test_facilitator_can_update_personal_record_fields_but_not_official_employment_fields(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $facilitator->facilitatorProfile()->create([
            'employee_number' => 'FAC-2026-011',
            'department' => 'NSTP Office',
            'designation' => 'NSTP Facilitator',
            'employment_status' => 'contractual',
        ]);

        $this->actingAs($facilitator)->get('/facilitator/profile')
            ->assertOk()
            ->assertSee('FAC-2026-011')
            ->assertSee('Official facilitator record');

        $this->actingAs($facilitator)->put('/facilitator/profile', [
            'name' => $facilitator->name,
            'email' => $facilitator->email,
            'employee_number' => 'ALTERED-NUMBER',
            'department' => 'Altered Department',
            'designation' => 'Altered Designation',
            'employment_status' => 'full_time',
            'contact_number' => '09181234567',
            'specialization' => 'Literacy education',
            'professional_summary' => 'Supports literacy-focused extension work.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facilitator_profiles', [
            'user_id' => $facilitator->id,
            'employee_number' => 'FAC-2026-011',
            'department' => 'NSTP Office',
            'designation' => 'NSTP Facilitator',
            'employment_status' => 'contractual',
            'contact_number' => '09181234567',
            'specialization' => 'Literacy education',
        ]);
    }

    public function test_employee_numbers_are_unique_and_required_for_new_facilitators(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $existing = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $existing->facilitatorProfile()->create(['employee_number' => 'FAC-UNIQUE-1']);

        $basePayload = [
            'name' => 'New Facilitator',
            'email' => 'new.facilitator@example.test',
            'role' => 'facilitator',
            'status' => 'active',
            'department' => 'NSTP Office',
            'designation' => 'NSTP Facilitator',
            'employment_status' => 'full_time',
        ];

        $this->actingAs($admin)->post('/admin/users', $basePayload)
            ->assertSessionHasErrors('employee_number');

        $this->actingAs($admin)->post('/admin/users', $basePayload + ['employee_number' => 'FAC-UNIQUE-1'])
            ->assertSessionHasErrors('employee_number');
    }
}
