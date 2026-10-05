<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSystemGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_staff_system_guides(): void
    {
        foreach (['/nstp-admin/system-guide', '/coordinator/system-guide', '/facilitator/system-guide'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_nstp_admin_can_open_their_system_guide(): void
    {
        $user = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/nstp-admin/system-guide')
            ->assertOk()
            ->assertSee('Coordinate every NSTP operation in the right order.')
            ->assertSee('Run NSTP administration')
            ->assertSee('NSTP Administrator checklist')
            ->assertSeeInOrder(['Profile &amp; Security', 'System Guide'], false)
            ->assertSee(route('nstp_admin.registrations.index'), false)
            ->assertSee(route('nstp_admin.reports.index'), false);
    }

    public function test_coordinator_can_open_their_system_guide(): void
    {
        $user = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/coordinator/system-guide')
            ->assertOk()
            ->assertSee('Keep your assigned NSTP component on track.')
            ->assertSee('Use your Coordinator portal')
            ->assertSee('Coordinator checklist')
            ->assertSeeInOrder(['Profile &amp; Security', 'System Guide'], false)
            ->assertSee(route('coordinator.sections.index'), false)
            ->assertSee(route('coordinator.performance.index'), false);
    }

    public function test_facilitator_can_open_their_system_guide(): void
    {
        $user = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/facilitator/system-guide')
            ->assertOk()
            ->assertSee('Manage your classes from attendance to final grades.')
            ->assertSee('Use your Facilitator portal')
            ->assertSee('Facilitator checklist')
            ->assertSeeInOrder(['Profile &amp; Security', 'System Guide'], false)
            ->assertSee(route('facilitator.attendance.index'), false)
            ->assertSee(route('facilitator.grades.index'), false);
    }

    public function test_staff_cannot_open_another_roles_system_guide(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/coordinator/system-guide')->assertForbidden();
        $this->actingAs($coordinator)->get('/facilitator/system-guide')->assertForbidden();
        $this->actingAs($facilitator)->get('/nstp-admin/system-guide')->assertForbidden();
    }
}
