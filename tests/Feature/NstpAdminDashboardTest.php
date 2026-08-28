<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NstpAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_nstp_admin_is_redirected_to_own_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'nstp_admin',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertRedirect('/nstp-admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_nstp_admin_can_view_own_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/nstp-admin/dashboard')
            ->assertOk()
            ->assertSee('NSTP Admin Dashboard');
    }

    public function test_nstp_admin_cannot_access_super_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_super_admin_cannot_access_nstp_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($user)->get('/nstp-admin/dashboard')->assertForbidden();
    }
}
