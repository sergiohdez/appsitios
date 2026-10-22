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
        abort_unless(
            $request->attributes->get('membership')->can_request
            || $request->attributes->get('membership')->can_approve
            || $request->attributes->get('membership')->can_manage,
            403
        );

        $sites = $request->attributes->get('tenant')->sites()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address']);

        return response()->json(['data' => $sites]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->attributes->get('membership')->can_manage, 403);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'timezone'],
            'form_schema' => ['sometimes', 'array'],
        ])->validate();

        $site = $request->attributes->get('tenant')->sites()->create($data);

        return response()->json(['data' => $site], 201);
    }
}
