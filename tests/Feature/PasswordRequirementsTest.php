<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_role_sees_the_live_password_requirements(): void
    {
        foreach ($this->rolePaths() as $role => $paths) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get($paths['profile'])
                ->assertOk()
                ->assertSee('Password requirements')
                ->assertSee('At least 12 characters')
                ->assertSee('At least one uppercase letter')
                ->assertSee('At least one lowercase letter')
                ->assertSee('At least one number')
                ->assertSee('At least one symbol')
                ->assertSee('Passwords match')
                ->assertSee('data-password-rules', false)
                ->assertSee('disabled', false);
        }
    }

    public function test_every_account_role_rejects_a_password_that_does_not_meet_requirements(): void
    {
        foreach ($this->rolePaths() as $role => $paths) {
            $user = User::factory()->create([
                'role' => $role,
                'status' => 'active',
                'password' => Hash::make('Old!Password2026'),
            ]);

            $this->actingAs($user)->put($paths['password'], [
                'current_password' => 'Old!Password2026',
                'password' => 'weakpassword',
                'password_confirmation' => 'weakpassword',
            ])->assertSessionHasErrors('password');

            $this->assertTrue(Hash::check('Old!Password2026', $user->fresh()->password));
        }
    }

    private function rolePaths(): array
    {
        return [
            'super_admin' => ['profile' => '/admin/profile', 'password' => '/admin/password'],
            'nstp_admin' => ['profile' => '/nstp-admin/profile', 'password' => '/nstp-admin/password'],
            'coordinator' => ['profile' => '/coordinator/profile', 'password' => '/coordinator/password'],
            'facilitator' => ['profile' => '/facilitator/profile', 'password' => '/facilitator/password'],
            'student' => ['profile' => '/student/profile', 'password' => '/student/password'],
        ];
    }
}
