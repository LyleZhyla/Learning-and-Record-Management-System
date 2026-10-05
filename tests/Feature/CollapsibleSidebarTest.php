<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollapsibleSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_portal_loads_the_collapsible_sidebar(): void
    {
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
                ->assertSee('class="main-nav"', false)
                ->assertSee('js/sidebar.js', false);
        }
    }

    public function test_sidebar_script_builds_accessible_persistent_menu_groups(): void
    {
        $script = file_get_contents(public_path('js/sidebar.js'));
        $styles = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('initializeNavigationGroups', $script);
        $this->assertStringContainsString("label.setAttribute('aria-expanded'", $script);
        $this->assertStringContainsString('snapie.sidebar.groups.', $script);
        $this->assertStringContainsString("event.key === 'Enter' || event.key === ' '", $script);
        $this->assertStringContainsString('.nav-group-content[hidden]', $styles);
        $this->assertStringContainsString('.nav-label.collapsed .nav-group-indicator', $styles);
    }
}
