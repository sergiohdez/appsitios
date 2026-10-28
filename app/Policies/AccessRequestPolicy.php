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

        return ($membership->canRequest() && $accessRequest->requester_id === $user->id)
            || ($membership->canApprove() && $membership->approvableSites()->whereKey($accessRequest->site_id)->exists());
    }

    public function decide(User $user, AccessRequest $accessRequest): bool
    {
        $membership = $user->membershipFor($accessRequest->organization_id);

        return $membership !== null
            && $membership->is_active
            && $membership->canApprove()
            && $accessRequest->requester_id !== $user->id
            && $accessRequest->status === AccessRequestStatus::PENDING
            && $membership->approvableSites()->whereKey($accessRequest->site_id)->exists();
    }
}
