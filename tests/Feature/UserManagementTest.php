<?php

namespace Tests\Feature;

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

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('User and Role Management');
    }

    public function test_super_admin_can_create_each_supported_role(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        foreach (array_keys(User::ROLE_LABELS) as $index => $role) {
            $this->actingAs($admin)->post('/admin/users', [
                'name' => "Test User {$index}",
                'email' => "role{$index}@example.test",
                'role' => $role,
                'status' => 'active',
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
}
