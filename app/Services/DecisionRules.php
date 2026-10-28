<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Membership;

class DecisionRules
{
    public function canDecide(AccessRequest $request, Membership $membership, bool $assignedToSite): bool
    {
        return $request->status === AccessRequestStatus::PENDING
            && $membership->is_active
            && $membership->canApprove()
            && $membership->organization_id === $request->organization_id
            && $assignedToSite
            && $request->requester_id !== $membership->user_id;
    }
}
