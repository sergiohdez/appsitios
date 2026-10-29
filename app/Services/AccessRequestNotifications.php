<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\User;
use App\Notifications\AccessRequestDecision;
use App\Notifications\AccessRequestSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccessRequestNotifications
{
    public function notifyApprovers(AccessRequest $accessRequest): void
    {
        $accessRequest->loadMissing(['organization', 'requester', 'site']);
        $site = $accessRequest->site;
        $organization = $accessRequest->organization;

        if ($accessRequest->status !== AccessRequestStatus::PENDING
            || ! $site
            || ! $organization
            || ! $accessRequest->requester) {
            return;
        }

        $approvers = $site->approvers()
            ->where('memberships.organization_id', $accessRequest->organization_id)
            ->where('memberships.role', MembershipRole::APPROVER->value)
            ->where('memberships.is_active', true)
            ->whereHas('user', function (Builder $query) use ($accessRequest): void {
                $query->where('users.is_active', true)
                    ->where('users.id', '!=', $accessRequest->requester_id);
            })
            ->with('user:id,name,email,is_active')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        $notification = new AccessRequestSubmitted(
            $organization->name,
            $site->name,
            $accessRequest->requester->name,
            $this->requestedPeriod($accessRequest, $site->timezone ?: $organization->timezone),
        );

        foreach ($approvers as $approver) {
            $this->send($approver, $notification, $accessRequest);
        }
    }

    public function notifyRequesterOfDecision(AccessRequest $accessRequest, User $reviewer): void
    {
        $accessRequest->loadMissing(['organization', 'requester', 'site']);
        $status = $accessRequest->status;

        if (! in_array($status, [AccessRequestStatus::APPROVED, AccessRequestStatus::REJECTED], true)
            || ! $accessRequest->requester
            || ! $accessRequest->organization
            || ! $accessRequest->site) {
            return;
        }

        $timezone = $accessRequest->site->timezone ?: $accessRequest->organization->timezone;
        $comment = trim((string) $accessRequest->decision_comment);

        $this->send(
            $accessRequest->requester,
            new AccessRequestDecision(
                $status,
                $accessRequest->organization->name,
                $accessRequest->site->name,
                $reviewer->name,
                $this->requestedPeriod($accessRequest, $timezone),
                $comment === '' ? null : $comment,
            ),
            $accessRequest,
        );
    }

    private function requestedPeriod(AccessRequest $accessRequest, string $timezone): string
    {
        $from = $accessRequest->requested_from->copy()->timezone($timezone)->format('d/m/Y H:i');
        $until = $accessRequest->requested_until->copy()->timezone($timezone)->format('d/m/Y H:i');

        return $from.' – '.$until;
    }

    private function send(User $recipient, AccessRequestSubmitted|AccessRequestDecision $notification, AccessRequest $accessRequest): void
    {
        try {
            $recipient->notify($notification);
        } catch (Throwable $exception) {
            Log::warning('Could not send an access request email notification.', [
                'organization_id' => $accessRequest->organization_id,
                'access_request_id' => $accessRequest->id,
                'recipient_id' => $recipient->id,
                'notification' => $notification::class,
                'exception' => $exception::class,
            ]);
        }
    }
}
