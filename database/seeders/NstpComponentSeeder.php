<?php

namespace Database\Seeders;

use App\Models\NstpComponent;
use Illuminate\Database\Seeder;

class NstpComponentSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            [
                'code' => 'CWTS',
                'name' => 'Civic Welfare Training Service',
                'description' => 'Programs and activities that contribute to the general welfare and improvement of community life.',
            ],
            [
                'code' => 'LTS',
                'name' => 'Literacy Training Service',
                'description' => 'Training students to teach literacy and numeracy skills to school children and other community sectors.',
            ],
            [
                'code' => 'ROTC',
                'name' => "Reserve Officers' Training Corps",
                'description' => 'Military training that prepares tertiary students for national defense and emergency response service.',
            ],
        ];

        foreach ($components as $component) {
            NstpComponent::firstOrCreate(
                ['code' => $component['code']],
                $component + ['default_section_capacity' => 40, 'is_active' => true],
            );
        }
    }
}
