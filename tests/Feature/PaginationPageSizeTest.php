<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $tenItems = $this->actingAs($admin)->get('/admin/users?per_page=10');

        $tenItems->assertOk()
            ->assertSee('aria-label="Items per page"', false)
            ->assertSee('<option value="10" selected>10</option>', false)
            ->assertSee('Staff 09')
            ->assertDontSee('Staff 10')
            ->assertSee('per_page=10', false);

        $twentyItems = $this->actingAs($admin)->get('/admin/users?per_page=20');

        $twentyItems->assertOk()
            ->assertSee('<option value="20" selected>20</option>', false)
            ->assertSee('Staff 19')
            ->assertDontSee('Staff 20');

        $allItems = $this->actingAs($admin)->get('/admin/users?per_page=all');

        $allItems->assertOk()
            ->assertSee('<option value="all" selected>All</option>', false)
            ->assertSee('Staff 21')
            ->assertSee('22 items');
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
}
