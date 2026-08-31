<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_every_account_portal_include_the_theme_control(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('images/snapie-landscape-light.png', false)
            ->assertSee('images/snapie-landscape-dark.png', false)
            ->assertSee('snapie.theme', false)
            ->assertSee('js/theme.js', false);

        foreach ([
            'super_admin' => '/admin/dashboard',
            'nstp_admin' => '/nstp-admin/dashboard',
            'coordinator' => '/coordinator/dashboard',
            'facilitator' => '/facilitator/dashboard',
            'student' => '/student/dashboard',
        ] as $role => $url) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('data-theme-toggle', false)
                ->assertDontSee('images/snapie-landscape-light.png', false)
                ->assertSee('class="brand-logo-dark"', false)
                ->assertSee('images/snapie-landscape-dark.png', false)
                ->assertSee('snapie.theme', false)
                ->assertSee('js/theme.js', false);
        }
    }

    public function test_theme_script_persists_the_selected_mode_and_tracks_system_preference(): void
    {
        $script = file_get_contents(public_path('js/theme.js'));

        $this->assertStringContainsString('localStorage.setItem(storageKey, theme)', $script);
        $this->assertStringContainsString('prefers-color-scheme: dark', $script);
        $this->assertStringContainsString('root.dataset.theme', $script);
    }
}
