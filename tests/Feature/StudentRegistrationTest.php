<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_a_student_account_with_a_unique_qr(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSee('auth-animated', false)
            ->assertSee('auth-register', false)
            ->assertSee('data-auth-direction="login"', false);

        $this->post('/register', [
            'name' => 'New NSTP Student',
            'email' => 'Student@Example.com',
            'password' => 'SecurePass!2026',
            'password_confirmation' => 'SecurePass!2026',
            'role' => 'super_admin',
        ])->assertRedirect('/student/dashboard');

        $student = User::where('email', 'student@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($student);
        $this->assertSame('student', $student->role);
        $this->assertSame('active', $student->status);
        $this->assertNotEmpty($student->student_qr_token);
    }

    public function test_registration_requires_a_strong_confirmed_password_and_unique_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com', 'role' => 'student']);

        $this->post('/register', [
            'name' => 'Another Student',
            'email' => 'existing@example.com',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_login_page_links_to_student_registration(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('register'))
            ->assertSee('data-auth-direction="register"', false);
    }
}
