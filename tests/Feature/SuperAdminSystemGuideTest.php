<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSystemGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_super_admin_system_guide(): void
    {
        $this->get('/admin/system-guide')->assertRedirect('/login');
    }

    public function test_non_super_admin_cannot_open_the_super_admin_system_guide(): void
    {
        $user = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($user)->get('/admin/system-guide')->assertForbidden();
    }

    public function test_super_admin_can_open_the_guided_system_overview(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/admin/system-guide')
            ->assertOk()
            ->assertSee('Run Smart NSTP with confidence.')
            ->assertSee('Set up and use the system')
            ->assertSee('Know what each role does')
            ->assertSee('Super Admin safety checklist')
            ->assertSee('Start interactive guided tour')
            ->assertSee('admin-tour.js')
            ->assertSee('data-admin-tour-page-title', false)
            ->assertSeeInOrder(['<p class="nav-label">Account</p>', 'Profile &amp; Security', 'System Guide'], false)
            ->assertSee(route('admin.users.create'), false)
            ->assertSee(route('admin.database-backup.index'), false);
    }

    public function test_super_admin_dashboard_links_to_the_system_guide(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Start guided tour')
            ->assertSee('data-start-admin-tour', false)
            ->assertSee(route('admin.system-guide'), false);
    }
}
