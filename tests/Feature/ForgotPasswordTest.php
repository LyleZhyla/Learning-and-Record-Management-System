<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_forgot_password_form(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot password?')->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot your password?')->assertSee('Send reset link');
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'student@example.test']);

        $this->post('/forgot-password', ['email' => '  STUDENT@EXAMPLE.TEST  '])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_receives_the_same_safe_response(): void
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent.');
        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'student@example.test',
            'password' => Hash::make('Old!Password2026'),
            'must_change_password' => true,
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertOk()->assertSee('Choose a new password');

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => strtoupper($user->email),
                'password' => 'New!Password2026',
                'password_confirmation' => 'New!Password2026',
            ])->assertRedirect('/login')->assertSessionHas('status');

            return true;
        });

        $user->refresh();
        $this->assertTrue(Hash::check('New!Password2026', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_invalid_token_does_not_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.test',
            'password' => Hash::make('Old!Password2026'),
        ]);

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'New!Password2026',
            'password_confirmation' => 'New!Password2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Old!Password2026', $user->fresh()->password));
    }

    public function test_reset_password_enforces_system_password_requirements(): void
    {
        $user = User::factory()->create(['email' => 'student@example.test']);

        $this->post('/reset-password', [
            'token' => 'any-token',
            'email' => $user->email,
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertSessionHasErrors('password');
    }
}
