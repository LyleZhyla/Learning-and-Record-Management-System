<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_built_in_roles_and_new_users_receive_the_matching_role(): void
    {
        $this->assertSame(5, Role::where('is_system', true)->count());

        $user = User::factory()->create(['role' => 'facilitator']);

        $this->assertSame('facilitator', $user->fresh()->accessRole?->slug);
        $this->assertTrue($user->hasPermission('learning.assessments'));
    }

    public function test_super_admin_can_create_and_update_a_custom_permission_role(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Assessment Assistant',
            'base_role' => 'facilitator',
            'description' => 'Can handle assessments but not attendance.',
            'permissions' => ['learning.assessments', 'learning.grades'],
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $role = Role::where('name', 'Assessment Assistant')->firstOrFail();
        $this->assertSame('facilitator', $role->base_role);
        $this->assertEqualsCanonicalizing(['learning.assessments', 'learning.grades'], $role->permissions);

        $this->actingAs($admin)->put('/admin/roles/'.$role->id, [
            'name' => 'Assessment Assistant',
            'base_role' => 'facilitator',
            'description' => 'Updated permissions.',
            'permissions' => ['learning.assessments'],
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['learning.assessments'], $role->fresh()->permissions);
    }

    public function test_custom_role_permissions_are_enforced_and_hidden_from_navigation(): void
    {
        $role = Role::create([
            'name' => 'Assessment Assistant',
            'slug' => 'assessment-assistant',
            'base_role' => 'facilitator',
            'permissions' => ['learning.assessments'],
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'facilitator',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/facilitator/materials')->assertForbidden();
        $this->actingAs($user)->get('/facilitator/assessments')->assertOk()
            ->assertSee('Assessments')
            ->assertDontSee('Learning Materials');
    }

    public function test_account_with_inactive_assigned_role_cannot_sign_in(): void
    {
        $role = Role::create([
            'name' => 'Inactive Facilitator',
            'slug' => 'inactive-facilitator',
            'base_role' => 'facilitator',
            'permissions' => [],
            'is_active' => false,
        ]);
        $user = User::factory()->create([
            'email' => 'inactive-role@example.test',
            'password' => Hash::make('Password!2026'),
            'role' => 'facilitator',
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password!2026'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
