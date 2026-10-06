<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FacilitatorRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_a_minimal_facilitator_record(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'email' => 'ana.facilitator@example.test',
            'role' => 'facilitator',
            'nstp_component_id' => $component->id,
            'contact_number' => '09171234567',
        ])->assertSessionHasNoErrors()->assertSessionHas('temporary_password');

        $facilitator = User::where('email', 'ana.facilitator@example.test')->firstOrFail();
        $temporaryPassword = $response->getSession()->get('temporary_password');
        $this->assertSame('Ana Facilitator', $facilitator->name);
        $this->assertSame('active', $facilitator->status);
        $this->assertSame($component->id, $facilitator->nstp_component_id);
        $this->assertTrue(Hash::check($temporaryPassword, $facilitator->password));
        $this->assertDatabaseHas('facilitator_profiles', [
            'user_id' => $facilitator->id,
            'contact_number' => '09171234567',
        ]);

        $this->actingAs($admin)->put('/admin/users/'.$facilitator->id, [
            'email' => 'ana.updated@example.test',
            'role' => 'facilitator',
            'nstp_component_id' => $component->id,
            'contact_number' => '09981234567',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $facilitator->id,
            'name' => 'Ana Updated',
            'email' => 'ana.updated@example.test',
            'nstp_component_id' => $component->id,
        ]);
        $this->assertDatabaseHas('facilitator_profiles', [
            'user_id' => $facilitator->id,
            'contact_number' => '09981234567',
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

    public function test_component_and_contact_number_are_required_for_new_facilitators(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/users', [
            'email' => 'new.facilitator@example.test',
            'role' => 'facilitator',
        ])->assertSessionHasErrors(['nstp_component_id', 'contact_number']);
    }
}
