<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PaginationPageSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_choose_the_number_of_items_shown_in_a_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        foreach (range(1, 21) as $number) {
            User::factory()->create([
                'name' => sprintf('Staff %02d', $number),
                'role' => 'facilitator',
                'status' => 'active',
            ]);
        }

        $tenItems = $this->actingAs($admin)->get('/admin/users?role=facilitator&per_page=10');

        $tenItems->assertOk()
            ->assertSee('aria-label="Items per page"', false)
            ->assertSeeInOrder(['aria-label="Items per page"', '<div class="table-wrap">'], false)
            ->assertSee('<option value="10" selected>10</option>', false)
            ->assertSee('Staff 09')
            ->assertSee('Staff 10')
            ->assertDontSee('Staff 11')
            ->assertSee('per_page=10', false);
        $this->assertSame(1, substr_count($tenItems->getContent(), 'aria-label="Items per page"'));

        $twentyItems = $this->actingAs($admin)->get('/admin/users?role=facilitator&per_page=20');

        $twentyItems->assertOk()
            ->assertSee('<option value="20" selected>20</option>', false)
            ->assertSee('Staff 20')
            ->assertDontSee('Staff 21');

        $allItems = $this->actingAs($admin)->get('/admin/users?role=facilitator&per_page=all');

        $allItems->assertOk()
            ->assertSee('<option value="all" selected>All</option>', false)
            ->assertSee('Staff 21')
            ->assertSee('21 items');
    }

    public function test_invalid_page_size_falls_back_to_the_list_default(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get('/admin/users?per_page=999');

        $response->assertOk()
            ->assertSee('<option value="10" selected>10</option>', false);
    }

    public function test_nstp_admin_account_lists_show_the_page_size_control_above_the_table(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'active']);

        foreach (['/nstp-admin/accounts', '/nstp-admin/students'] as $uri) {
            $response = $this->actingAs($admin)->get($uri);

            $response->assertOk()
                ->assertSeeInOrder(['aria-label="Items per page"', '<div class="table-wrap">'], false);
            $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Items per page"'));
        }
    }

    public function test_reports_center_shows_the_page_size_control_above_the_preview_table(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'active']);

        $response = $this->actingAs($admin)->get('/admin/reports?type=students');

        $response->assertOk()
            ->assertSeeInOrder(['class="report-preview-page-size"', 'class="table-wrap"'], false);
        $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Items per page"'));
    }

    public function test_every_paginated_view_declares_a_top_page_size_control(): void
    {
        $acceptedTopMarkers = [
            'pagination.top-page-size',
            'pagination.page-size',
            'account-list-page-size',
            'service-admin-page-size',
            'report-preview-page-size',
            'assessment-top-page-size',
        ];

        $missingTopControls = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->mapWithKeys(fn ($file) => [$file->getRelativePathname() => File::get($file->getPathname())])
            ->filter(fn (string $contents, string $path) => $path !== 'pagination'.DIRECTORY_SEPARATOR.'compact.blade.php'
                && str_contains($contents, '->links('))
            ->reject(fn (string $contents) => collect($acceptedTopMarkers)->contains(
                fn (string $marker) => str_contains($contents, $marker)
            ))
            ->keys()
            ->values();

        $this->assertSame([], $missingTopControls->all(), 'Paginated views without a top page-size control: '.$missingTopControls->implode(', '));
        $this->assertStringContainsString(
            '@if($showPageSize ?? false)',
            File::get(resource_path('views/pagination/compact.blade.php')),
            'The shared bottom paginator must not render the page-size control by default.'
        );
    }
}
