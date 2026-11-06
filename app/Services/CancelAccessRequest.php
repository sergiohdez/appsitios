<?php

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelAccessRequest
{
    public function __construct(private readonly CancellationRules $rules)
    {
    }

    public function cancel(AccessRequest $accessRequest, User $canceller, ?string $comment): void
    {
        DB::transaction(function () use ($accessRequest, $canceller, $comment): void {
            $locked = AccessRequest::query()->whereKey($accessRequest->id)->lockForUpdate()->firstOrFail();
            $membership = $canceller->membershipFor($locked->organization_id);
            $assigned = $membership?->approvableSites()->whereKey($locked->site_id)->exists() ?? false;

            if (! $membership || ! $this->rules->canCancel($locked, $membership, $assigned)) {
                throw new \DomainException($this->failureMessage($locked));
            }

            $locked->fill([
                'status' => AccessRequestStatus::CANCELLED,
                'decided_by' => $canceller->id,
                'decided_at' => now(),
                'decision_comment' => $comment,
            ])->save();

            AuditEvent::create([
                'organization_id' => $locked->organization_id,
                'actor_user_id' => $canceller->id,
                'event_type' => 'access_request.cancelled',
                'subject_type' => AccessRequest::class,
                'subject_id' => $locked->id,
                'metadata' => ['status' => AccessRequestStatus::CANCELLED->value],
            ]);
        });
    }

    private function failureMessage(AccessRequest $request): string
    {
        if ($request->status === AccessRequestStatus::APPROVED
            && $request->requested_from !== null
            && ! $request->requested_from->isFuture()) {
            return 'La solicitud aprobada ya no se puede cancelar porque la fecha de inicio ya pasó.';
        }

        if (! in_array($request->status, [AccessRequestStatus::PENDING, AccessRequestStatus::APPROVED], true)) {
            return 'La solicitud ya no se puede cancelar porque está en estado '.$request->status->value.'.';
        }

        return 'No está autorizado para cancelar esta solicitud.';
    }
}
