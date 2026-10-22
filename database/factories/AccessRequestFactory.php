<?php

namespace Database\Factories;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccessRequest> */
class AccessRequestFactory extends Factory
{
    protected $model = AccessRequest::class;

    public function definition(): array
    {
        $membership = Membership::factory()->create();
        $site = Site::factory()->create(['organization_id' => $membership->organization_id]);

        return [
            'organization_id' => $membership->organization_id,
            'site_id' => $site->id,
            'requester_id' => $membership->user_id,
            'status' => AccessRequestStatus::PENDING,
            'purpose' => fake()->sentence(),
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
            'form_data' => [],
        ];
    }
}
