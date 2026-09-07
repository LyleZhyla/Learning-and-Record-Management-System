<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTableSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_list_pages_load_the_shared_column_sorter(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        foreach (['/admin/users', '/admin/students', '/admin/reports', '/admin/system-logs', '/admin/archives'] as $url) {
            $this->actingAs($superAdmin)->get($url)
                ->assertOk()
                ->assertSee('js/table-sort.js', false);
        }
    }

    public function test_nstp_admin_list_pages_load_the_shared_column_sorter(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        foreach (['/nstp-admin/accounts', '/nstp-admin/students', '/nstp-admin/sections', '/nstp-admin/reports', '/nstp-admin/announcements'] as $url) {
            $this->actingAs($nstpAdmin)->get($url)
                ->assertOk()
                ->assertSee('js/table-sort.js', false);
        }
    }

    public function test_coordinator_list_pages_load_the_shared_column_sorter(): void
    {
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'is_active' => true,
        ]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $component->id,
        ]);

        foreach (['/coordinator/components', '/coordinator/accounts', '/coordinator/sections', '/coordinator/attendance', '/coordinator/reports'] as $url) {
            $this->actingAs($coordinator)->get($url)
                ->assertOk()
                ->assertSee('js/table-sort.js', false);
        }
    }

    public function test_facilitator_list_pages_load_the_shared_column_sorter(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        foreach (['/facilitator/students', '/facilitator/attendance', '/facilitator/materials', '/facilitator/assessments', '/facilitator/answer-sheet-scanner', '/facilitator/reports'] as $url) {
            $this->actingAs($facilitator)->get($url)
                ->assertOk()
                ->assertSee('js/table-sort.js', false);
        }
    }

    public function test_column_sorter_supports_accessible_multi_type_sorting(): void
    {
        $script = file_get_contents(public_path('js/table-sort.js'));

        $this->assertStringContainsString("document.querySelectorAll('table.data-table')", $script);
        $this->assertStringContainsString("header.setAttribute('aria-sort'", $script);
        $this->assertStringContainsString('numberPattern', $script);
        $this->assertStringContainsString('datePattern', $script);
        $this->assertStringContainsString('timePattern', $script);
        $this->assertStringContainsString('localeCompare', $script);
    }
}
