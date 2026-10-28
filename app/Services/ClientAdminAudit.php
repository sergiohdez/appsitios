<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Organization;

class ClientAdminAudit
{
    public function record(
        Organization $organization,
        string $eventType,
        string $subjectType,
        string $subjectId,
        array $metadata = [],
    ): void {
        AuditEvent::query()->create([
            'organization_id' => $organization->id,
            'actor_user_id' => auth()->id(),
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => mb_substr($subjectId, 0, 64),
            'metadata' => ['source' => 'client-admin'] + $metadata,
            'created_at' => now(),
        ]);
    }
}
