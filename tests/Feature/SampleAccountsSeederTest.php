<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SampleAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SampleAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_sample_account_for_each_non_student_role(): void
    {
        $this->seed(SampleAccountsSeeder::class);
        $this->seed(SampleAccountsSeeder::class);

        $emails = array_column(SampleAccountsSeeder::ACCOUNTS, 'email');
        $accounts = User::with('nstpComponent')->whereIn('email', $emails)->get();

        $this->assertCount(4, $accounts);
        $this->assertEqualsCanonicalizing(
            ['super_admin', 'nstp_admin', 'coordinator', 'facilitator'],
            $accounts->pluck('role')->all(),
        );
        $this->assertFalse($accounts->contains('role', 'student'));
        $this->assertSame('CWTS', $accounts->firstWhere('role', 'coordinator')->nstpComponent?->code);
        $this->assertTrue($accounts->every(fn (User $user) => Hash::check(SampleAccountsSeeder::PASSWORD, $user->password)));
    }
}
