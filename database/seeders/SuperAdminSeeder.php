<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('super_admin.email');
        $password = config('super_admin.password');

        if (! $email || ! $password) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD before running the SuperAdminSeeder.');
        }

        User::firstOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => config('super_admin.name'),
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'status' => 'active',
                'must_change_password' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
