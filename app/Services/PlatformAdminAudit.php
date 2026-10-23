<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

class PlatformAdminAudit
{
    public function record(
        Organization $organization,
        string $eventType,
        string $subjectType,
        string $subjectId,
        array $metadata = []
    ): void {
        $actorId = auth()->id();

        AuditEvent::query()->create([
            'organization_id' => $organization->id,
            'actor_user_id' => $actorId,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => mb_substr($subjectId, 0, 64),
            'metadata' => ['source' => 'platform-admin'] + $metadata,
            'created_at' => now(),
        ]);

        Log::notice('Platform administrator accessed tenant data.', [
            'actor_user_id' => $actorId,
            'organization_id' => $organization->id,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }
}
