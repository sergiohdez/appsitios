<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessRequestTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_only_sees_requests_from_their_tenant(): void
    {
        $tenantA = Organization::factory()->create();
        $tenantB = Organization::factory()->create();
        $requester = User::factory()->create();
        Membership::factory()->create(['organization_id' => $tenantA->id, 'user_id' => $requester->id, 'role' => MembershipRole::REQUESTER]);

        $siteA = Site::factory()->create(['organization_id' => $tenantA->id]);
        $siteB = Site::factory()->create(['organization_id' => $tenantB->id]);
        $otherRequester = User::factory()->create();
        Membership::factory()->create(['organization_id' => $tenantB->id, 'user_id' => $otherRequester->id, 'role' => MembershipRole::REQUESTER]);
        $own = AccessRequest::factory()->create(['organization_id' => $tenantA->id, 'site_id' => $siteA->id, 'requester_id' => $requester->id]);
        AccessRequest::factory()->create(['organization_id' => $tenantB->id, 'site_id' => $siteB->id, 'requester_id' => $otherRequester->id]);

        Sanctum::actingAs($requester);

        $response = $this->getJson('/api/v1/access-requests');

        $response->assertOk()->assertJsonFragment(['id' => $own->id]);
        self::assertCount(1, $response->json('data.data'));
    }

    public function test_requester_cannot_approve_their_own_request(): void
    {
        $tenant = Organization::factory()->create();
        $requester = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $tenant->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $site = Site::factory()->create(['organization_id' => $tenant->id]);
        $membership->approvableSites()->attach($site->id, ['organization_id' => $tenant->id]);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $tenant->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/approve")
            ->assertForbidden();
    }

    public function test_site_approver_can_approve_and_reject_requests_created_by_requesters(): void
    {
        $tenant = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $tenant->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $tenant->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $approver = User::factory()->create();
        $approverMembership = Membership::factory()->create([
            'organization_id' => $tenant->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $tenant->id]);

        $toApprove = AccessRequest::factory()->create([
            'organization_id' => $tenant->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
        ]);
        $toReject = AccessRequest::factory()->create([
            'organization_id' => $tenant->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
        ]);

        Sanctum::actingAs($approver);

        $inbox = $this->getJson('/api/v1/access-requests')->assertOk();
        $items = collect($inbox->json('data.data'))->keyBy('id');
        self::assertTrue($items[$toApprove->id]['can_decide']);
        self::assertTrue($items[$toReject->id]['can_decide']);

        $this->postJson("/api/v1/access-requests/{$toApprove->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/access-requests/{$toReject->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }
}
