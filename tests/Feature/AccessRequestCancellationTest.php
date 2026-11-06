<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Notifications\AccessRequestDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessRequestCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_cancel_own_pending_request(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel", [
            'comment' => 'Ya no necesito el acceso.',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $fresh = $accessRequest->fresh();
        self::assertSame(AccessRequestStatus::CANCELLED, $fresh->status);
        self::assertSame($requester->id, $fresh->decided_by);
        self::assertNotNull($fresh->decided_at);
        self::assertSame('Ya no necesito el acceso.', $fresh->decision_comment);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_user_id' => $requester->id,
            'event_type' => 'access_request.cancelled',
            'subject_id' => $accessRequest->id,
        ]);
        Notification::assertNotSentTo($requester, AccessRequestDecision::class);
    }

    public function test_requester_can_cancel_own_approved_request_with_future_start(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'requested_from' => now()->addDays(3),
            'requested_until' => now()->addDays(4),
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        self::assertSame(AccessRequestStatus::CANCELLED, $accessRequest->fresh()->status);
    }

    public function test_requester_cannot_cancel_own_approved_request_that_already_started(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'requested_from' => now()->subDay(),
            'requested_until' => now()->addDay(),
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('errors.request.0', 'La solicitud aprobada ya no se puede cancelar porque la fecha de inicio ya pasó.');

        self::assertSame(AccessRequestStatus::APPROVED, $accessRequest->fresh()->status);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'access_request.cancelled',
            'subject_id' => $accessRequest->id,
        ]);
    }

    public function test_assigned_approver_can_cancel_pending_and_future_approved_requests(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$requester, $approver] = $this->createRequesterAndApprover($organization, $site);

        $pending = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $approved = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'requested_from' => now()->addDays(5),
            'requested_until' => now()->addDays(6),
        ]);

        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/access-requests/{$pending->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/access-requests/{$approved->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        Notification::assertSentTo($requester, AccessRequestDecision::class, function (AccessRequestDecision $notification): bool {
            return $notification->status === AccessRequestStatus::CANCELLED;
        });
        self::assertSame(AccessRequestStatus::CANCELLED, $pending->fresh()->status);
        self::assertSame(AccessRequestStatus::CANCELLED, $approved->fresh()->status);
    }

    public function test_assigned_approver_cannot_cancel_approved_request_that_already_started(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$requester, $approver] = $this->createRequesterAndApprover($organization, $site);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'requested_from' => now()->subHours(2),
            'requested_until' => now()->addDay(),
        ]);

        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('errors.request.0', 'La solicitud aprobada ya no se puede cancelar porque la fecha de inicio ya pasó.');
        self::assertSame(AccessRequestStatus::APPROVED, $accessRequest->fresh()->status);
    }

    public function test_approver_without_site_assignment_cannot_cancel(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $otherApprover = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $otherApprover->id,
            'role' => MembershipRole::APPROVER,
        ]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($otherApprover);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel")->assertForbidden();
        self::assertSame(AccessRequestStatus::PENDING, $accessRequest->fresh()->status);
    }

    public function test_other_requester_cannot_cancel_someone_elses_request(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();
        $intruder = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $intruder->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel")->assertForbidden();
        self::assertSame(AccessRequestStatus::PENDING, $accessRequest->fresh()->status);
    }

    public function test_rejected_and_already_cancelled_requests_cannot_be_cancelled(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();

        $rejected = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::REJECTED,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $cancelled = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::CANCELLED,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/access-requests/{$rejected->id}/cancel")->assertForbidden();
        $this->postJson("/api/v1/access-requests/{$cancelled->id}/cancel")->assertForbidden();
        self::assertSame(AccessRequestStatus::REJECTED, $rejected->fresh()->status);
        self::assertSame(AccessRequestStatus::CANCELLED, $cancelled->fresh()->status);
    }

    public function test_approver_creator_can_cancel_own_pending_request(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $approver = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $membership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $approver->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($approver);

        $this->postJson("/api/v1/access-requests/{$accessRequest->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_cancel_request_from_another_tenant(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();
        $otherOrganization = Organization::factory()->create();
        $otherSite = Site::factory()->create(['organization_id' => $otherOrganization->id]);
        $otherRequester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherRequester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $foreign = AccessRequest::factory()->create([
            'organization_id' => $otherOrganization->id,
            'site_id' => $otherSite->id,
            'requester_id' => $otherRequester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/access-requests/{$foreign->id}/cancel")->assertNotFound();
    }

    public function test_index_exposes_can_cancel_only_when_cancellable(): void
    {
        Notification::fake();
        [$organization, $site, $requester] = $this->setupTenantWithSite();
        $pending = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $approvedFuture = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'requested_from' => now()->addDays(3),
            'requested_until' => now()->addDays(4),
        ]);
        $approvedPast = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'requested_from' => now()->subDay(),
            'requested_until' => now()->addDay(),
        ]);
        $rejected = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::REJECTED,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($requester);

        $response = $this->getJson('/api/v1/access-requests')->assertOk();
        $items = collect($response->json('data.data'))->keyBy('id');

        self::assertTrue($items[$pending->id]['can_cancel']);
        self::assertTrue($items[$approvedFuture->id]['can_cancel']);
        self::assertFalse($items[$approvedPast->id]['can_cancel']);
        self::assertFalse($items[$rejected->id]['can_cancel']);
    }

    public function test_approver_sees_can_cancel_for_assigned_site_only(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $assignedSite = Site::factory()->create(['organization_id' => $organization->id]);
        $otherSite = Site::factory()->create(['organization_id' => $organization->id]);
        [$requester, $approver] = $this->createRequesterAndApprover($organization, $assignedSite);

        $assigned = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $assignedSite->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $unassigned = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $otherSite->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($approver);

        $response = $this->getJson('/api/v1/access-requests')->assertOk();
        $items = collect($response->json('data.data'))->keyBy('id');

        self::assertTrue($items[$assigned->id]['can_cancel']);
        self::assertFalse($items[$unassigned->id]['can_cancel'] ?? false);
    }

    /** @return array{Organization, Site, User} */
    private function setupTenantWithSite(): array
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);

        return [$organization, $site, $requester];
    }

    /** @return array{User, User} */
    private function createRequesterAndApprover(Organization $organization, Site $site): array
    {
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $approver = User::factory()->create();
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        return [$requester, $approver];
    }
}
