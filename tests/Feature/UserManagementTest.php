<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Staff Account Management')
            ->assertSee($staff->name)
            ->assertDontSee($student->name)
            ->assertSee('Edit')
            ->assertSee('Delete');

        $this->actingAs($admin)->get('/admin/students')
            ->assertOk()->assertSee($student->name)->assertDontSee($staff->name)->assertSee('Download QR');
    }

    public function test_super_admin_can_create_each_supported_role(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);

        foreach (array_keys(User::ROLE_LABELS) as $index => $role) {
            $this->actingAs($admin)->post('/admin/users', [
                'name' => "Test User {$index}",
                'email' => "role{$index}@example.test",
                'role' => $role,
                'status' => 'active',
                'nstp_component_id' => $role === 'coordinator' ? $component->id : null,
                'password' => 'Secure!Password2026',
                'password_confirmation' => 'Secure!Password2026',
            ])->assertSessionHasNoErrors();

            $this->assertDatabaseHas('users', ['email' => "role{$index}@example.test", 'role' => $role]);
        }
    }

    public function test_super_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status")
            ->assertSessionHasErrors('status');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_the_last_active_super_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'nstp_admin',
            'status' => 'active',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    public function test_password_reset_requires_a_change_on_next_login(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'must_change_password' => false]);

        $this->actingAs($admin)->put("/admin/users/{$student->id}/password", [
            'password' => 'New!Password2026',
            'password_confirmation' => 'New!Password2026',
        ])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertTrue(Hash::check('New!Password2026', $student->password));
        $this->assertTrue($student->must_change_password);
    }

    public function test_super_admin_can_delete_an_account_without_deleting_institutional_content(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Preserved Assessment', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published']);

        $this->actingAs($admin)->delete('/admin/users/'.$facilitator->id)
            ->assertRedirect('/admin/users')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $facilitator->id]);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'created_by' => $admin->id]);
        $this->assertDatabaseHas('nstp_sections', ['id' => $section->id, 'facilitator_id' => null]);
    }

    public function test_super_admin_cannot_delete_self_or_the_last_active_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $otherAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHasErrors('user');
        $this->actingAs($admin)->delete('/admin/users/'.$otherAdmin->id)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $otherAdmin->id]);

        $thirdAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'inactive']);
        $this->actingAs($admin)->delete('/admin/users/'.$thirdAdmin->id)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
