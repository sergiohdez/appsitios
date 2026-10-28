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

        $tenant = $request->attributes->get('tenant');
        $formVersion = $tenant->requestFormVersions()->orderByDesc('version')->first();
        $schema = $formVersion?->schema ?? ['fields' => []];

        $sites = $tenant->sites()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name', 'code', 'address']);

        $sites->each(function ($site) use ($formVersion, $schema): void {
            $site->setAttribute('organization_form_version_id', $formVersion?->id);
            $site->setAttribute('form_schema', $schema);
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

        return response()->json(['data' => $site], 201);
    }
}
