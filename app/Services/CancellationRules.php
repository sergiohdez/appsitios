<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Membership;

class CancellationRules
{
    public function canCancel(AccessRequest $request, Membership $membership, bool $assignedToSite): bool
    {
        if (! $membership->is_active) {
            return false;
        }

        if ($membership->organization_id !== $request->organization_id) {
            return false;
        }

        if (! in_array($request->status, [AccessRequestStatus::PENDING, AccessRequestStatus::APPROVED], true)) {
            return false;
        }

        $isCreator = $request->requester_id === $membership->user_id && $membership->canRequest();
        $isApprover = $membership->canApprove() && $assignedToSite;

        if (! ($isCreator || $isApprover)) {
            return false;
        }

        if ($request->status === AccessRequestStatus::APPROVED) {
            return $request->requested_from !== null && $request->requested_from->isFuture();
        }

        return true;
    }
}
