<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminReadabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_portal_loads_the_readability_guardrail(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('css/super-admin-readability.css', false)
            ->assertSee('js/super-admin-readability.js', false);
    }

    public function test_other_account_portals_do_not_load_the_super_admin_guardrail(): void
    {
        foreach ([
            'nstp_admin' => '/nstp-admin/dashboard',
            'coordinator' => '/coordinator/dashboard',
            'facilitator' => '/facilitator/dashboard',
            'student' => '/student/dashboard',
        ] as $role => $url) {
            $user = User::factory()->create([
                'role' => $role,
                'status' => 'active',
            ]);

            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertDontSee('super-admin-readability', false);
        }
    }

    public function test_guardrail_enforces_the_expected_minimum_without_targeting_icons(): void
    {
        $styles = file_get_contents(public_path('css/super-admin-readability.css'));
        $script = file_get_contents(public_path('js/super-admin-readability.js'));

        $this->assertStringContainsString('--super-admin-minimum-font-size: 12px', $styles);
        $this->assertStringContainsString('--super-admin-minimum-font-size: 13px', $styles);
        $this->assertStringContainsString("root.classList.contains('portal-super-admin')", $script);
        $this->assertStringContainsString('renderedSize < minimumFontSize', $script);
        $this->assertStringContainsString('.nav-icon, .metric-icon', $script);
        $this->assertStringContainsString('MutationObserver', $script);
    }
}
