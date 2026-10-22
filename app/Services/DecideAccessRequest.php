<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DecideAccessRequest
{
    public function __construct(private readonly DecisionRules $rules)
    {
    }

    public function decide(AccessRequest $accessRequest, User $reviewer, AccessRequestStatus $decision, ?string $comment): void
    {
        if (! in_array($decision, [AccessRequestStatus::APPROVED, AccessRequestStatus::REJECTED], true)) {
            throw new \InvalidArgumentException('La decisión solicitada no es válida.');
        }

        DB::transaction(function () use ($accessRequest, $reviewer, $decision, $comment): void {
            $locked = AccessRequest::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();
            $membership = $reviewer->membershipFor($locked->organization_id);
            $assigned = $membership?->approvableSites()->whereKey($locked->site_id)->exists() ?? false;

            if (! $membership || ! $this->rules->canDecide($locked, $membership, $assigned)) {
                throw new \DomainException('No está autorizado para decidir esta solicitud.');
            }

            $locked->fill([
                'status' => $decision,
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
                'decision_comment' => $comment,
            ])->save();

            AuditEvent::create([
                'organization_id' => $locked->organization_id,
                'actor_user_id' => $reviewer->id,
                'event_type' => 'access_request.'.$decision->value,
                'subject_type' => AccessRequest::class,
                'subject_id' => $locked->id,
                'metadata' => ['status' => $decision->value],
            ]);
        });
    }
}
