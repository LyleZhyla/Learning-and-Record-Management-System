<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_management_roles_can_open_the_landing_page_editor(): void
    {
        foreach ([
            'super_admin' => 'admin.landing-page.edit',
            'nstp_admin' => 'nstp_admin.landing-page.edit',
            'coordinator' => 'coordinator.landing-page.edit',
        ] as $role => $route) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Landing Page Editor')
                ->assertSee('Publish landing page');
        }
    }

    public function test_facilitator_cannot_open_the_landing_page_editor(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($facilitator)
            ->get('/coordinator/landing-page')
            ->assertForbidden();
    }

    public function test_coordinator_can_publish_content_that_appears_on_the_public_landing_page(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $content = array_replace(SystemSetting::LANDING_PAGE_DEFAULTS, [
            'hero_line_1' => 'TAU students lead with purpose.',
            'contact_body' => 'Visit the updated NSTP Office information desk.',
        ]);

        $this->actingAs($coordinator)
            ->put(route('coordinator.landing-page.update'), $content)
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'landing_page_content',
            'updated_by' => $coordinator->id,
        ]);

        $this->post(route('logout'));

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('TAU students lead with purpose.')
            ->assertSee('Visit the updated NSTP Office information desk.');
    }

    public function test_landing_page_editor_rejects_invalid_media_urls(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $content = array_replace(SystemSetting::LANDING_PAGE_DEFAULTS, [
            'hero_video_url' => 'not-a-url',
        ]);

        $this->actingAs($admin)
            ->from(route('nstp_admin.landing-page.edit'))
            ->put(route('nstp_admin.landing-page.update'), $content)
            ->assertRedirect(route('nstp_admin.landing-page.edit'))
            ->assertSessionHasErrors('hero_video_url');
    }
}
