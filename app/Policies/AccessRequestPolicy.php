<?php

namespace App\Policies;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;

class AccessRequestPolicy
{
    public function view(User $user, AccessRequest $accessRequest): bool
    {
        $membership = $user->membershipFor($accessRequest->organization_id);

        if (! $membership) {
            return false;
        }

        return $membership->can_manage
            || ($membership->can_request && $accessRequest->requester_id === $user->id)
            || ($membership->can_approve && $membership->approvableSites()->whereKey($accessRequest->site_id)->exists());
    }

    public function decide(User $user, AccessRequest $accessRequest): bool
    {
        $membership = $user->membershipFor($accessRequest->organization_id);

        return $membership !== null
            && $membership->can_approve
            && $accessRequest->requester_id !== $user->id
            && $accessRequest->status === AccessRequestStatus::PENDING
            && $membership->approvableSites()->whereKey($accessRequest->site_id)->exists();
    }
}
