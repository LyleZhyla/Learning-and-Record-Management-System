<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleAccountsSeeder extends Seeder
{
    public const PASSWORD = 'Demo!Account2026';

    public const ACCOUNTS = [
        [
            'name' => 'Juan Dela Cruz',
            'email' => 'student.demo@smartnstp.local',
            'role' => 'student',
        ],
        [
            'name' => 'Maria Santos',
            'email' => 'facilitator.demo@smartnstp.local',
            'role' => 'facilitator',
        ],
        [
            'name' => 'Carlo Reyes',
            'email' => 'coordinator.demo@smartnstp.local',
            'role' => 'coordinator',
        ],
        [
            'name' => 'Angela Mendoza',
            'email' => 'nstpadmin.demo@smartnstp.local',
            'role' => 'nstp_admin',
        ],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role' => $account['role'],
                    'status' => 'active',
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
