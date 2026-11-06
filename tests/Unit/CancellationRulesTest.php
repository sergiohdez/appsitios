<?php

namespace Tests\Unit;

use App\Enums\AccessRequestStatus;
use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Services\CancellationRules;
use Tests\TestCase;

class CancellationRulesTest extends TestCase
{
    public function test_creator_can_cancel_pending_and_future_approved(): void
    {
        $rules = new CancellationRules();

        $pending = $this->request(AccessRequestStatus::PENDING, now()->addDay(), 10);
        $approvedFuture = $this->request(AccessRequestStatus::APPROVED, now()->addDay(), 10);
        $membership = $this->membership(10, MembershipRole::REQUESTER);

        self::assertTrue($rules->canCancel($pending, $membership, false));
        self::assertTrue($rules->canCancel($approvedFuture, $membership, false));
    }

    public function test_approved_past_cannot_be_cancelled_even_by_creator_or_assigned_approver(): void
    {
        $rules = new CancellationRules();
        $approvedPast = $this->request(AccessRequestStatus::APPROVED, now()->subHour(), 10);
        $creator = $this->membership(10, MembershipRole::REQUESTER);
        $approver = $this->membership(20, MembershipRole::APPROVER);

        self::assertFalse($rules->canCancel($approvedPast, $creator, false));
        self::assertFalse($rules->canCancel($approvedPast, $approver, true));
    }

    public function test_assigned_approver_can_cancel_others_pending_but_unassigned_cannot(): void
    {
        $rules = new CancellationRules();
        $request = $this->request(AccessRequestStatus::PENDING, now()->addDay(), 10);
        $assigned = $this->membership(20, MembershipRole::APPROVER);
        $unassigned = $this->membership(30, MembershipRole::APPROVER);

        self::assertTrue($rules->canCancel($request, $assigned, true));
        self::assertFalse($rules->canCancel($request, $unassigned, false));
    }

    public function test_rejected_cancelled_and_other_tenant_cannot_be_cancelled(): void
    {
        $rules = new CancellationRules();
        $membership = $this->membership(10, MembershipRole::REQUESTER);

        $rejected = $this->request(AccessRequestStatus::REJECTED, now()->addDay(), 10);
        $cancelled = $this->request(AccessRequestStatus::CANCELLED, now()->addDay(), 10);
        $otherTenant = $this->membership(10, MembershipRole::REQUESTER, 'tenant-b');

        self::assertFalse($rules->canCancel($rejected, $membership, true));
        self::assertFalse($rules->canCancel($cancelled, $membership, true));
        self::assertFalse($rules->canCancel($this->request(AccessRequestStatus::PENDING, now()->addDay(), 10), $otherTenant, true));
    }

    private function request(AccessRequestStatus $status, \DateTimeInterface $requestedFrom, int $requesterId): AccessRequest
    {
        $request = new AccessRequest([
            'organization_id' => 'tenant-a',
            'requester_id' => $requesterId,
            'status' => $status,
        ]);
        $request->forceFill(['requested_from' => $requestedFrom]);

        return $request;
    }

    private function membership(int $userId, MembershipRole $role, string $organizationId = 'tenant-a'): Membership
    {
        return new Membership([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
