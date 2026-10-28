<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $membership = $request->attributes->get('membership');
        abort_unless($membership->canRequest() || $membership->canApprove() || $membership->canManage(), 403);

        $sites = $request->attributes->get('tenant')->sites()
            ->where('is_active', true)
            ->orderBy('name')
            ->with('currentFormVersion:id,organization_id,site_id,version,schema')
            ->get(['id', 'organization_id', 'name', 'code', 'address', 'form_schema'])
            ->map(function ($site) {
                $site->setAttribute('form_version_id', $site->currentFormVersion?->id);
                $site->setAttribute('form_schema', $site->currentFormVersion?->schema ?? $site->form_schema ?? ['fields' => []]);

                return $site;
            });

        return response()->json(['data' => $sites]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->canManage(), 403);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'timezone'],
            'form_schema' => ['prohibited'],
        ])->validate();

        $site = $request->attributes->get('tenant')->sites()->create($data);
        $site->formVersions()->create([
            'organization_id' => $site->organization_id,
            'version' => 1,
            'schema' => ['fields' => []],
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        return response()->json(['data' => $site], 201);
    }
}
