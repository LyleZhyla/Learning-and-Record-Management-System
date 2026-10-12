<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastNameOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_directories_are_ordered_by_last_name(): void
    {
        $superAdmin = User::factory()->create(['name' => 'Root Administrator', 'role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['name' => 'NSTP Administrator', 'role' => 'nstp_admin', 'status' => 'active']);
        User::factory()->create(['name' => 'Aaron Zulu', 'role' => 'facilitator', 'status' => 'active']);
        User::factory()->create(['name' => 'Zoe Alpha', 'role' => 'facilitator', 'status' => 'active']);
        User::factory()->create(['name' => 'Ben Young', 'role' => 'student', 'status' => 'active']);
        User::factory()->create(['name' => 'Yna Bravo', 'role' => 'student', 'status' => 'active']);

        $this->actingAs($superAdmin)->get('/admin/users?role=facilitator')
            ->assertOk()
            ->assertSeeInOrder(['Zoe Alpha', 'Aaron Zulu']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts')
            ->assertOk()
            ->assertSeeInOrder(['Zoe Alpha', 'Aaron Zulu']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/students')
            ->assertOk()
            ->assertSeeInOrder(['Yna Bravo', 'Ben Young']);
    }

    public function test_last_name_sort_key_ignores_common_name_suffixes(): void
    {
        $user = User::factory()->create(['name' => 'Juan Dela Cruz Jr.']);

        $this->assertSame('cruz', $user->last_name_sort_key);
    }
}
