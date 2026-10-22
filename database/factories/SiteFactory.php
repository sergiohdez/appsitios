<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city().' - '.fake()->streetName(),
            'code' => strtoupper(fake()->unique()->bothify('SITE-###')),
            'address' => fake()->address(),
            'timezone' => 'America/Bogota',
            'form_schema' => [],
            'is_active' => true,
        ];
    }
}
