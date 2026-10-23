<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\AccessRequestAttachment;
use App\Models\Organization;
use App\Services\PlatformAdminAudit;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformAdminAccessRequestController extends Controller
{
    public function show(
        Organization $organization,
        AccessRequest $accessRequest,
        PlatformAdminAudit $audit
    ): Response {
        $accessRequest = $organization->accessRequests()
            ->with(['site', 'requester', 'people', 'attachments'])
            ->findOrFail($accessRequest->id);

        $audit->record($organization, 'platform.access_request.viewed', AccessRequest::class, $accessRequest->id);

        return response()->view('platform.organizations.request', compact('organization', 'accessRequest'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function downloadAttachment(
        Organization $organization,
        AccessRequest $accessRequest,
        AccessRequestAttachment $attachment,
        PlatformAdminAudit $audit
    ): StreamedResponse {
        $accessRequest = $organization->accessRequests()->findOrFail($accessRequest->id);
        $attachment = $accessRequest->attachments()->findOrFail($attachment->id);
        $expectedPrefix = "tenants/{$organization->id}/requests/{$accessRequest->id}/";
        $disk = Storage::disk('private');

        abort_unless(str_starts_with($attachment->storage_path, $expectedPrefix), 404);
        abort_unless($disk->exists($attachment->storage_path), 404);

        $audit->record($organization, 'platform.access_request.attachment_downloaded', AccessRequestAttachment::class, $attachment->id);

        return $disk->download($attachment->storage_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
