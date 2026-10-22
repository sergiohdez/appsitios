<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return Storage::disk('private')->download($attachment->storage_path, $attachment->original_name);
    }
}
