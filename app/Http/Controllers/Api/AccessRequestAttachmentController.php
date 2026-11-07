<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRequestAttachment;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AccessRequestAttachmentController extends Controller
{
    public function __invoke(Request $request, string $requestId, string $attachmentId)
    {
        $accessRequest = $request->attributes->get('tenant')->accessRequests()->findOrFail($requestId);
        Gate::authorize('view', $accessRequest);

        $attachment = $accessRequest->attachments()->findOrFail($attachmentId);
        $expectedPrefix = "tenants/{$accessRequest->organization_id}/requests/{$accessRequest->id}/";
        $disk = Storage::disk('private');

        abort_unless(str_starts_with($attachment->storage_path, $expectedPrefix), 404);
        abort_unless($disk->exists($attachment->storage_path), 404);

        AuditEvent::create([
            'organization_id' => $accessRequest->organization_id,
            'actor_user_id' => $request->user()->id,
            'event_type' => 'access_request.attachment_downloaded',
            'subject_type' => AccessRequestAttachment::class,
            'subject_id' => $attachment->id,
            'metadata' => ['access_request_id' => $accessRequest->id],
        ]);

        return $disk->download($attachment->storage_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
