<?php

namespace Database\Seeders;

use App\Models\NstpComponent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleAccountsSeeder extends Seeder
{
    public const PASSWORD = 'Demo!Account2026';

    public const ACCOUNTS = [
        [
            'name' => 'Demo Super Administrator',
            'email' => 'superadmin.demo@smartnstp.local',
            'role' => 'super_admin',
            'component' => null,
        ],
        [
            'name' => 'Maria Santos',
            'email' => 'facilitator.demo@smartnstp.local',
            'role' => 'facilitator',
            'component' => null,
        ],
        [
            'name' => 'Carlo Reyes',
            'email' => 'coordinator.demo@smartnstp.local',
            'role' => 'coordinator',
            'component' => 'CWTS',
        ],
        [
            'name' => 'Angela Mendoza',
            'email' => 'nstpadmin.demo@smartnstp.local',
            'role' => 'nstp_admin',
            'component' => null,
        ],
    ];

    public function run(): void
    {
        $this->call(NstpComponentSeeder::class);
        $componentIds = NstpComponent::pluck('id', 'code');

        foreach (self::ACCOUNTS as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make(self::PASSWORD),
                    'role' => $account['role'],
                    'nstp_component_id' => $account['component'] ? $componentIds->get($account['component']) : null,
                    'status' => 'active',
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->forceFill([
                'name' => $account['name'],
                'role' => $account['role'],
                'nstp_component_id' => $account['component'] ? $componentIds->get($account['component']) : null,
                'status' => 'active',
            ])->save();
        }
    }
}
