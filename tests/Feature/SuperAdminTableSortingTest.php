<?php

namespace Tests\Feature;

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

    public function test_column_sorter_is_limited_to_the_super_admin_layout(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/reports')
            ->assertOk()
            ->assertDontSee('js/table-sort.js', false);
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
