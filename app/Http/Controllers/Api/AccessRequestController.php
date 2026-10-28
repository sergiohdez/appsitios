<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\AccessRequest;
use App\Models\AuditEvent;
use App\Models\Organization;
use App\Services\DecideAccessRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccessRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $membership = $request->attributes->get('membership');
        $user = $request->user();

        abort_unless($membership->canRequest() || $membership->canApprove(), 403);

        $query = $tenant->accessRequests()->with([
            'site:id,name',
            'people:id,access_request_id,full_name,company',
            'formVersion:id,version,schema',
            'organizationFormVersion:id,version,schema',
            'attachments:id,access_request_id,original_name,form_field_key',
        ]);
        $approvableSiteIds = $membership->canApprove()
            ? $membership->approvableSites()->pluck('sites.id')->all()
            : [];

        if (! $membership->canManage()) {
            $query->where(function (Builder $scope) use ($membership, $user): void {
                if ($membership->canRequest()) {
                    $scope->where('requester_id', $user->id);
                }

                if ($membership->canApprove()) {
                    $siteIds = $membership->approvableSites()->select('sites.id');
                    $scope->orWhere(function (Builder $pending) use ($siteIds): void {
                        $pending->where('status', AccessRequestStatus::PENDING->value)
                            ->whereIn('site_id', $siteIds);
                    });
                }
            });
        }

        $requests = $query->latest()->paginate(25);
        $requests->getCollection()->each(function (AccessRequest $accessRequest) use ($membership, $user, $approvableSiteIds): void {
            $canDecide = $membership->is_active
                && $membership->canApprove()
                && $accessRequest->status === AccessRequestStatus::PENDING
                && $accessRequest->requester_id !== $user->id
                && in_array($accessRequest->site_id, $approvableSiteIds, true);

            $accessRequest->setAttribute('can_decide', $canDecide);
        });

        return response()->json(['data' => $requests]);
    }

    public function store(StoreAccessRequestRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $data = $request->validated();

        $storedPaths = [];

        try {
            $accessRequest = DB::transaction(function () use ($request, $tenant, $data, &$storedPaths): AccessRequest {
                $organization = Organization::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $site = $organization->sites()->whereKey($data['site_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
                $formVersion = $organization->requestFormVersions()->orderByDesc('version')->first();

                if (isset($data['organization_form_version_id']) && $formVersion?->id !== $data['organization_form_version_id']) {
                    throw ValidationException::withMessages([
                        'organization_form_version_id' => 'El formulario del cliente se actualizó. Recarga la página antes de enviar la solicitud.',
                    ]);
                }

                $formData = $data['form_data'] ?? [];
                $customFiles = $request->file('form_data', []) ?? [];

                foreach (array_keys($customFiles) as $fieldKey) {
                    unset($formData[$fieldKey]);
                }

                $accessRequest = $tenant->accessRequests()->create([
                    'site_id' => $data['site_id'],
                    'organization_form_version_id' => $formVersion?->id,
                    'requester_id' => $request->user()->id,
                    'status' => AccessRequestStatus::PENDING,
                    'purpose' => $data['purpose'],
                    'requested_from' => $data['requested_from'],
                    'requested_until' => $data['requested_until'],
                    'form_data' => $formData,
                ]);

                foreach ($data['people'] as $person) {
                    $accessRequest->people()->create($person);
                }

                foreach ($request->file('attachments', []) ?? [] as $file) {
                    $path = $file->store("tenants/{$tenant->id}/requests/{$accessRequest->id}", 'private');
                    $storedPaths[] = $path;
                    $accessRequest->attachments()->create([
                        'storage_path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }

                foreach ($customFiles as $fieldKey => $file) {
                    $path = $file->store("tenants/{$tenant->id}/requests/{$accessRequest->id}", 'private');
                    $storedPaths[] = $path;
                    $accessRequest->attachments()->create([
                        'storage_path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                        'form_field_key' => $fieldKey,
                    ]);
                }

                AuditEvent::create([
                    'organization_id' => $tenant->id,
                    'actor_user_id' => $request->user()->id,
                    'event_type' => 'access_request.created',
                    'subject_type' => AccessRequest::class,
                    'subject_id' => $accessRequest->id,
                    'metadata' => ['status' => AccessRequestStatus::PENDING->value],
                ]);

                return $accessRequest;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }

        return response()->json(['data' => $accessRequest->load(['site:id,name', 'people', 'attachments', 'formVersion:id,version,schema', 'organizationFormVersion:id,version,schema'])], 201);
    }

    public function show(Request $request, string $requestId): JsonResponse
    {
        $accessRequest = $request->attributes->get('tenant')->accessRequests()
            ->with(['site:id,name', 'people', 'attachments', 'formVersion:id,version,schema', 'organizationFormVersion:id,version,schema'])
            ->findOrFail($requestId);

        Gate::authorize('view', $accessRequest);

        return response()->json(['data' => $accessRequest]);
    }

    public function approve(Request $request, string $requestId, DecideAccessRequest $decider): JsonResponse
    {
        return $this->decide($request, $requestId, AccessRequestStatus::APPROVED, $decider);
    }

    public function reject(Request $request, string $requestId, DecideAccessRequest $decider): JsonResponse
    {
        return $this->decide($request, $requestId, AccessRequestStatus::REJECTED, $decider);
    }

    private function decide(Request $request, string $requestId, AccessRequestStatus $status, DecideAccessRequest $decider): JsonResponse
    {
        $validated = $request->validate(['comment' => ['nullable', 'string', 'max:1000']]);
        $accessRequest = $request->attributes->get('tenant')->accessRequests()->findOrFail($requestId);
        Gate::authorize('decide', $accessRequest);

        try {
            $decider->decide($accessRequest, $request->user(), $status, $validated['comment'] ?? null);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return response()->json(['data' => $accessRequest->fresh()->load('site:id,name')]);
    }
}
