<?php

namespace Tests\Unit;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Services\DecisionRules;
use PHPUnit\Framework\TestCase;

class DecisionRulesTest extends TestCase
{
    public function test_assigned_active_approver_can_decide_another_users_pending_request(): void
    {
        $request = new AccessRequest(['organization_id' => 'tenant-a', 'requester_id' => 10, 'status' => AccessRequestStatus::PENDING]);
        $membership = new Membership(['organization_id' => 'tenant-a', 'user_id' => 20, 'can_approve' => true, 'is_active' => true]);

        self::assertTrue((new DecisionRules())->canDecide($request, $membership, true));
    }

    public function test_requester_cannot_approve_their_own_request_even_if_assigned(): void
    {
        $request = new AccessRequest(['organization_id' => 'tenant-a', 'requester_id' => 10, 'status' => AccessRequestStatus::PENDING]);
        $membership = new Membership(['organization_id' => 'tenant-a', 'user_id' => 10, 'can_approve' => true, 'is_active' => true]);

        self::assertFalse((new DecisionRules())->canDecide($request, $membership, true));
    }

    public function test_approver_from_another_tenant_cannot_decide(): void
    {
        $request = new AccessRequest(['organization_id' => 'tenant-a', 'requester_id' => 10, 'status' => AccessRequestStatus::PENDING]);
        $membership = new Membership(['organization_id' => 'tenant-b', 'user_id' => 20, 'can_approve' => true, 'is_active' => true]);

        self::assertFalse((new DecisionRules())->canDecide($request, $membership, true));
    }
}
