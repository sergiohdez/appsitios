<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Models\ClientBulkImport;
use App\Models\ClientBulkImportRecord;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClientBulkImportService
{
    private const EXPIRATION_HOURS = 24;

    public function __construct(private readonly ClientBulkImportFileParser $parser)
    {
    }

    public function create(Organization $organization, User $actor, string $entity, UploadedFile $file): ClientBulkImport
    {
        if (! in_array($entity, ['users', 'sites'], true)) {
            throw ValidationException::withMessages(['file' => 'El tipo de importación no es válido.']);
        }

        $parsed = $this->parser->parse($file, $entity);
        $import = null;

        DB::transaction(function () use ($organization, $actor, $entity, $parsed, &$import): void {
            $import = ClientBulkImport::query()->create([
                'organization_id' => $organization->id,
                'created_by_user_id' => $actor->id,
                'entity' => $entity,
                'status' => 'pending',
                'total_records' => count($parsed['records']),
                'expires_at' => now()->addHours(self::EXPIRATION_HOURS),
            ]);

            foreach (array_chunk($parsed['records'], 500) as $chunk) {
                $now = now();
                $rows = array_map(fn (array $record): array => [
                    'client_bulk_import_id' => $import->id,
                    'source_row' => $record['source_row'],
                    'data' => json_encode($record, JSON_THROW_ON_ERROR),
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);

                DB::table('client_bulk_import_records')->insert($rows);
            }
        });

        return $import;
    }

    public function purgeExpired(string $organizationId): void
    {
        $expired = ClientBulkImport::query()
            ->where('organization_id', $organizationId)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $import) {
            if ($import->errors_path) {
                Storage::disk('local')->delete($import->errors_path);
            }

            $import->delete();
        }
    }

    /**
     * Process exactly one row so the browser can report live record progress.
     *
     * @return array<string, mixed>
     */
    public function processNext(
        Organization $organization,
        User $actor,
        string $importId,
        ClientAdminAudit $audit,
        SendPasswordSetupInvitation $invitations,
    ): array {
        $response = DB::transaction(function () use ($organization, $actor, $importId, $audit): array {
            $import = ClientBulkImport::query()
                ->where('organization_id', $organization->id)
                ->where('created_by_user_id', $actor->id)
                ->whereKey($importId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($import->expires_at->isPast()) {
                throw ValidationException::withMessages(['import' => 'La importación venció. Vuelve a cargar el archivo.']);
            }

            if ($import->status === 'completed') {
                return $this->progress($import);
            }

            $record = $import->records()
                ->where('status', 'pending')
                ->orderBy('source_row')
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $import->status = 'completed';
                $import->save();

                return $this->progress($import);
            }

            $errors = [];
            $createdUserId = null;

            try {
                $createdUserId = DB::transaction(function () use ($import, $record, $organization, $actor, $audit): ?int {
                    return $import->entity === 'users'
                        ? $this->importUser($record->data, $organization, $actor, $import, $audit)
                        : $this->importSite($record->data, $organization, $import, $audit);
                });
            } catch (ValidationException $exception) {
                $errors = collect($exception->errors())->flatten()->map(fn ($message): string => (string) $message)->unique()->values()->all();
            } catch (QueryException) {
                $errors = [$import->entity === 'users'
                    ? 'No se pudo guardar el usuario porque su correo ya fue registrado.'
                    : 'No se pudo guardar el sitio porque su código ya existe para este cliente.'];
            }

            if ($errors !== []) {
                $record->forceFill(['status' => 'failed', 'errors' => $errors])->save();
                $import->failed_records++;
            } else {
                $record->forceFill(['status' => 'succeeded', 'errors' => null])->save();
                $import->successful_records++;
            }

            $import->processed_records++;
            $import->status = $import->processed_records >= $import->total_records ? 'completed' : 'in_progress';

            if ($import->failed_records > 0) {
                $this->writeErrorsFile($import);
            }

            $import->save();

            return $this->progress($import, $record, $errors, $createdUserId);
        });

        if (! empty($response['created_user_id'])) {
            $user = User::query()->find($response['created_user_id']);
            unset($response['created_user_id']);

            if ($user) {
                $sent = $invitations->send($user);
                $audit->record($organization, 'client.user.access_link_sent', User::class, (string) $user->id, [
                    'sent' => $sent,
                    'bulk_import_id' => $importId,
                ]);

                if (! $sent && isset($response['record'])) {
                    $response['record']['warnings'][] = "Usuario guardado, pero no se pudo enviar la invitación a {$user->email}. Reenvíala desde la lista de usuarios.";
                }
            }
        }

        if (($response['failed_records'] ?? 0) > 0) {
            $response['errors_url'] = route('client.imports.errors', $importId);
        }

        return $response;
    }

    public function findForDownload(Organization $organization, User $actor, string $importId): ClientBulkImport
    {
        $import = ClientBulkImport::query()
            ->where('organization_id', $organization->id)
            ->where('created_by_user_id', $actor->id)
            ->whereKey($importId)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_if($import->failed_records === 0, 404);

        if (! $import->errors_path || ! Storage::disk('local')->exists($import->errors_path)) {
            $this->writeErrorsFile($import);
            $import->save();
        }

        abort_unless($import->errors_path, 404);

        return $import;
    }

    private function importUser(array $row, Organization $organization, User $actor, ClientBulkImport $import, ClientAdminAudit $audit): ?int
    {
        $role = $this->normalizeRole((string) ($row['role'] ?? ''));
        $sitesText = trim((string) ($row['sites'] ?? ''));
        $siteIds = [];

        if ($role === MembershipRole::APPROVER->value) {
            $siteIds = $this->resolveApproverSites($sitesText, $organization);
        } elseif ($sitesText !== '' && in_array($role, [MembershipRole::REQUESTER->value, ''], true)) {
            throw ValidationException::withMessages(['sites' => 'La columna Sitios asignados solo se usa para usuarios con perfil Aprobador.']);
        }

        $data = [
            'name' => trim((string) ($row['name'] ?? '')),
            'email' => mb_strtolower(trim((string) ($row['email'] ?? ''))),
            'role' => $role,
            'site_ids' => $siteIds,
        ];

        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])],
            'site_ids' => $role === MembershipRole::APPROVER->value
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'site_ids.*' => ['required', 'ulid', Rule::exists('sites', 'id')->where('organization_id', $organization->id)->where('is_active', true)],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ese correo electrónico ya está asociado a una cuenta.',
            'role.required' => 'El perfil es obligatorio.',
            'role.in' => 'El perfil debe ser Aprobador o Solicitante.',
            'site_ids.required' => 'Indica al menos un sitio para el usuario Aprobador.',
            'site_ids.min' => 'Indica al menos un sitio para el usuario Aprobador.',
            'site_ids.*.exists' => 'Uno de los sitios asignados no existe o está inactivo para este cliente.',
        ])->validate();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::random(64),
        ]);
        $membership = $organization->memberships()->create([
            'user_id' => $user->id,
            'role' => MembershipRole::from($data['role']),
            'is_active' => true,
        ]);

        if ($membership->canApprove()) {
            $membership->approvableSites()->sync(collect($siteIds)->mapWithKeys(
                fn (string $siteId): array => [$siteId => ['organization_id' => $organization->id]],
            )->all());
        }

        $audit->record($organization, 'client.user.created', Membership::class, $membership->id, [
            'role' => $membership->role->value,
            'bulk_import_id' => $import->id,
        ]);

        return $user->id;
    }

    private function importSite(array $row, Organization $organization, ClientBulkImport $import, ClientAdminAudit $audit): null
    {
        $data = [
            'name' => trim((string) ($row['name'] ?? '')),
            'code' => trim((string) ($row['code'] ?? '')) ?: null,
            'address' => trim((string) ($row['address'] ?? '')) ?: null,
            'timezone' => trim((string) ($row['timezone'] ?? '')),
        ];

        Validator::make($data, [
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('sites', 'code')->where('organization_id', $organization->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
        ], [
            'name.required' => 'El nombre del sitio es obligatorio.',
            'name.max' => 'El nombre del sitio no puede superar 180 caracteres.',
            'code.unique' => 'Ese código ya está asociado a otro sitio de este cliente.',
            'code.max' => 'El código no puede superar 80 caracteres.',
            'address.max' => 'La dirección no puede superar 255 caracteres.',
            'timezone.required' => 'La zona horaria es obligatoria.',
            'timezone.timezone' => 'La zona horaria no es válida. Usa un identificador IANA, por ejemplo America/Bogota.',
        ])->validate();

        $site = $organization->sites()->create($data);
        $audit->record($organization, 'client.site.created', Site::class, $site->id, [
            'bulk_import_id' => $import->id,
        ]);

        return null;
    }

    private function resolveApproverSites(string $siteReferences, Organization $organization): array
    {
        if ($siteReferences === '') {
            throw ValidationException::withMessages(['sites' => 'Para un Aprobador, indica uno o más sitios separados por punto y coma.']);
        }

        $sites = $organization->sites()->where('is_active', true)->get(['id', 'name', 'code']);
        $resolved = [];

        foreach (preg_split('/[;|]/', $siteReferences) ?: [] as $reference) {
            $reference = mb_strtolower(trim($reference));

            if ($reference === '') {
                continue;
            }

            $matches = $sites->filter(fn (Site $site): bool => $site->code !== null
                && mb_strtolower(trim($site->code)) === $reference);

            if ($matches->isEmpty()) {
                $matches = $sites->filter(fn (Site $site): bool => mb_strtolower(trim($site->name)) === $reference);
            }

            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['sites' => "El sitio '{$reference}' coincide con varios registros. Usa un código de sitio único."]);
            }

            if ($matches->isEmpty()) {
                throw ValidationException::withMessages(['sites' => "El sitio '{$reference}' no existe o está inactivo para este cliente."]);
            }

            $resolved[] = (string) $matches->first()->id;
        }

        $resolved = array_values(array_unique($resolved));

        if ($resolved === []) {
            throw ValidationException::withMessages(['sites' => 'Para un Aprobador, indica uno o más sitios separados por punto y coma.']);
        }

        return $resolved;
    }

    private function normalizeRole(string $role): string
    {
        $normalized = preg_replace('/[^a-z]/', '', Str::ascii(mb_strtolower(trim($role)))) ?? '';

        return match ($normalized) {
            'aprobador', 'approver' => MembershipRole::APPROVER->value,
            'solicitante', 'solicitador', 'requester' => MembershipRole::REQUESTER->value,
            default => $normalized,
        };
    }

    private function writeErrorsFile(ClientBulkImport $import): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('No se pudo preparar el archivo de errores.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        $headers = $import->entity === 'users'
            ? ['Fila de origen', 'Nombre', 'Correo electrónico', 'Perfil', 'Sitios asignados', 'Error']
            : ['Fila de origen', 'Nombre', 'Código', 'Dirección', 'Zona horaria', 'Error'];
        fputcsv($stream, $headers, ',', '"', '\\');

        $import->records()->where('status', 'failed')->orderBy('source_row')->get()->each(function (ClientBulkImportRecord $record) use ($stream, $import): void {
            $data = $record->data;
            $fields = $import->entity === 'users'
                ? [$record->source_row, $data['name'] ?? '', $data['email'] ?? '', $data['role'] ?? '', $data['sites'] ?? '']
                : [$record->source_row, $data['name'] ?? '', $data['code'] ?? '', $data['address'] ?? '', $data['timezone'] ?? ''];
            $fields[] = implode(' | ', $record->errors ?? []);
            fputcsv($stream, $fields, ',', '"', '\\');
        });

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new RuntimeException('No se pudo preparar el archivo de errores.');
        }

        $path = "client-bulk-imports/{$import->organization_id}/{$import->id}/registros-fallidos.csv";

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('No se pudo guardar el archivo de errores.');
        }

        $import->errors_path = $path;
    }

    private function progress(
        ClientBulkImport $import,
        ?ClientBulkImportRecord $record = null,
        array $errors = [],
        ?int $createdUserId = null,
    ): array {
        $recordResult = null;

        if ($record) {
            $recordResult = [
                'source_row' => $record->source_row,
                'imported' => $errors === [],
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        return [
            'record' => $recordResult,
            'processed_records' => $import->processed_records,
            'total_records' => $import->total_records,
            'successful_records' => $import->successful_records,
            'failed_records' => $import->failed_records,
            'complete' => $import->status === 'completed',
            'created_user_id' => $createdUserId,
        ];
    }
}
