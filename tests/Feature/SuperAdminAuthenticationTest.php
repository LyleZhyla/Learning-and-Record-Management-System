<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_super_admin_can_sign_in(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_student_signs_in_to_the_student_portal(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertRedirect('/student/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_super_admin_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'super_admin',
            'status' => 'inactive',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Old!Password2026'),
            'role' => 'super_admin',
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/admin/password', [
            'current_password' => 'Old!Password2026',
            'password' => 'New!Password2026',
            'password_confirmation' => 'New!Password2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New!Password2026', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
