<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientAdminAudit;
use App\Services\ClientBulkImportService;
use App\Services\SendPasswordSetupInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientBulkImportController extends Controller
{
    public function preview(Request $request, string $entity, ClientBulkImportService $imports): JsonResponse
    {
        $organization = $request->attributes->get('tenant');
        $file = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ])['file'];
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'El archivo debe tener extensión CSV, XLS o XLSX.']);
        }

        $imports->purgeExpired($organization->id);
        $import = $imports->create($organization, $request->user(), $entity, $file);

        return response()->json([
            'id' => $import->id,
            'total_records' => $import->total_records,
            'next_url' => route('client.imports.next', $import->id),
            'errors_url' => route('client.imports.errors', $import->id),
        ], 201);
    }

    public function next(
        Request $request,
        string $importId,
        ClientBulkImportService $imports,
        ClientAdminAudit $audit,
        SendPasswordSetupInvitation $invitations,
    ): JsonResponse {
        $progress = $imports->processNext(
            $request->attributes->get('tenant'),
            $request->user(),
            $importId,
            $audit,
            $invitations,
        );

        return response()->json($progress);
    }

    public function errors(Request $request, string $importId, ClientBulkImportService $imports): StreamedResponse
    {
        $import = $imports->findForDownload($request->attributes->get('tenant'), $request->user(), $importId);
        abort_unless(Storage::disk('local')->exists($import->errors_path), 404);

        return Storage::disk('local')->download(
            $import->errors_path,
            "{$import->entity}-registros-fallidos.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
