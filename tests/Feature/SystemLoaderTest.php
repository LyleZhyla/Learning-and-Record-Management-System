<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_and_portal_pages_include_the_snapie_loader(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-system-loader', false)
            ->assertSee('images/characters/snapie-loading.png', false)
            ->assertSee('js/system-loader.js', false);

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
                ->assertSee('data-system-loader', false)
                ->assertSee('images/characters/snapie-loading.png', false)
                ->assertSee('js/system-loader.js', false);
        }
    }

    public function test_loader_script_handles_page_transitions_and_safe_exclusions(): void
    {
        $script = file_get_contents(public_path('js/system-loader.js'));

        $this->assertStringContainsString("document.addEventListener('click'", $script);
        $this->assertStringContainsString("document.addEventListener('submit'", $script);
        $this->assertStringContainsString("window.addEventListener('beforeunload', show)", $script);
        $this->assertStringContainsString("window.addEventListener('pageshow', hide)", $script);
        $this->assertStringContainsString("link.hasAttribute('download')", $script);
        $this->assertStringContainsString("link.dataset.noLoader", $script);
        $this->assertStringContainsString("window.setTimeout(hide, 15000)", $script);
    }
}
