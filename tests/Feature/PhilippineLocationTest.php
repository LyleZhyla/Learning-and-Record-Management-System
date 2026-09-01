<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhilippineLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_renders_clickable_local_province_options(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('value="031400000" data-name="Bulacan"', false)
            ->assertSee('value="130000000" data-name="Metro Manila (NCR)"', false);
    }

    public function test_location_endpoints_proxy_psgc_data(): void
    {
        Http::fake([
            'https://psgc.gitlab.io/api/provinces/031400000/cities-municipalities/' => Http::response([
                ['code' => '031410000', 'name' => 'City of Malolos'],
            ]),
            'https://psgc.gitlab.io/api/cities-municipalities/031410000/barangays/' => Http::response([
                ['code' => '031410001', 'name' => 'Anilao'],
            ]),
        ]);

        $this->getJson('/locations/provinces/031400000/cities')
            ->assertOk()
            ->assertJsonPath('0.name', 'City of Malolos');
        $this->getJson('/locations/cities/031410000/barangays')
            ->assertOk()
            ->assertJsonPath('0.name', 'Anilao');
    }
}
