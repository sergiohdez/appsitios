<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\ClientAdminAudit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientSiteController extends Controller
{
    public function index(Request $request): ViewContract
    {
        $sites = $request->attributes->get('tenant')->sites()
            ->withCount(['accessRequests', 'approvers'])
            ->orderBy('name')
            ->paginate(15);

        return view('client.sites.index', compact('sites'));
    }

    public function create(): ViewContract
    {
        return view('client.sites.form', ['site' => new Site(), 'editing' => false]);
    }

    public function edit(Request $request, string $siteId): ViewContract
    {
        $site = $request->attributes->get('tenant')->sites()->findOrFail($siteId);

        return view('client.sites.form', ['site' => $site, 'editing' => true]);
    }

    public function store(Request $request, ClientAdminAudit $audit): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $data = $this->validatedSite($request, $tenant->id);
        $schema = $data['form_schema'];
        unset($data['form_schema']);
        $site = DB::transaction(function () use ($tenant, $data, $schema, $request): Site {
            $site = $tenant->sites()->create($data);
            $this->publishFormSchema($site, $schema, $request->user()->id);

            return $site;
        });
        $audit->record($tenant, 'client.site.created', Site::class, $site->id);

        return redirect()->route('client.sites.index')->with('status', 'Sitio creado.');
    }

    public function update(Request $request, string $siteId, ClientAdminAudit $audit): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $site = $tenant->sites()->findOrFail($siteId);
        $data = $this->validatedSite($request, $tenant->id, $site->id);
        $schema = $data['form_schema'];
        unset($data['form_schema']);
        $site = DB::transaction(function () use ($tenant, $siteId, $data, $schema, $request): Site {
            $site = $tenant->sites()->whereKey($siteId)->lockForUpdate()->firstOrFail();
            $site->update($data);
            $this->publishFormSchema($site, $schema, $request->user()->id);

            return $site;
        });
        $audit->record($tenant, 'client.site.updated', Site::class, $site->id, [
            'is_active' => $site->is_active,
        ]);

        return redirect()->route('client.sites.index')->with('status', 'Cambios del sitio guardados.');
    }

    public function destroy(Request $request, string $siteId, ClientAdminAudit $audit): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $site = $tenant->sites()->withCount(['accessRequests', 'approvers'])->findOrFail($siteId);

        if ($site->access_requests_count > 0 || $site->approvers_count > 0) {
            throw ValidationException::withMessages([
                'site' => 'No se puede eliminar este sitio porque tiene solicitudes de acceso o usuarios aprobadores asignados. Desactívalo si ya no debe usarse.',
            ]);
        }

        try {
            DB::transaction(function () use ($tenant, $site, $audit): void {
                $lockedSite = $tenant->sites()->whereKey($site->id)->lockForUpdate()->firstOrFail();
                $hasRequests = $lockedSite->accessRequests()->exists();
                $hasApprovers = DB::table('site_approvers')->where('site_id', $lockedSite->id)->exists();

                if ($hasRequests || $hasApprovers) {
                    throw ValidationException::withMessages([
                        'site' => 'No se puede eliminar este sitio porque tiene solicitudes de acceso o usuarios aprobadores asignados. Desactívalo si ya no debe usarse.',
                    ]);
                }

                $lockedSite->delete();
                $audit->record($tenant, 'client.site.deleted', Site::class, $lockedSite->id);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'site' => 'No se pudo eliminar el sitio porque todavía tiene información relacionada.',
            ]);
        }

        return redirect()->route('client.sites.index')->with('status', 'Sitio eliminado.');
    }

    private function validatedSite(Request $request, string $tenantId, ?string $siteId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('sites', 'code')->where('organization_id', $tenantId)->ignore($siteId)],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'is_active' => [$siteId ? 'required' : 'prohibited', 'boolean'],
            'form_fields' => ['sometimes', 'array'],
            'form_fields.*.key' => ['nullable', 'string', 'regex:/^field_[A-Za-z0-9_-]{8,60}$/', 'distinct'],
            'form_fields.*.label' => ['required', 'string', 'max:120'],
            'form_fields.*.type' => ['required', Rule::in(['text', 'textarea', 'date', 'select', 'file'])],
            'form_fields.*.required' => ['sometimes', 'boolean'],
            'form_fields.*.options' => ['nullable', 'string'],
        ]);

        $data['is_active'] = $siteId ? $request->boolean('is_active') : true;
        $fields = [];

        foreach ($data['form_fields'] ?? [] as $index => $field) {
            $options = $field['type'] === 'select'
                ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $field['options'] ?? '') ?: []), fn (string $option): bool => $option !== '')))
                : [];

            if ($field['type'] === 'select' && $options === []) {
                throw ValidationException::withMessages([
                    "form_fields.{$index}.options" => 'Agrega al menos una opción para este campo de selección.',
                ]);
            }

            $fields[] = [
                'key' => ($field['key'] ?? null) ?: 'field_'.Str::lower(Str::random(24)),
                'label' => trim($field['label']),
                'type' => $field['type'],
                'required' => $request->boolean("form_fields.{$index}.required"),
                'options' => $options,
            ];
        }

        unset($data['form_fields']);
        $data['form_schema'] = ['fields' => $fields];

        return $data;
    }

    private function publishFormSchema(Site $site, array $schema, int $publisherId): void
    {
        $latest = $site->formVersions()->orderByDesc('version')->first();

        if ($latest?->schema === $schema) {
            return;
        }

        $site->formVersions()->create([
            'organization_id' => $site->organization_id,
            'version' => ($latest?->version ?? 0) + 1,
            'schema' => $schema,
            'published_by' => $publisherId,
            'published_at' => now(),
        ]);
        $site->update(['form_schema' => $schema]);
    }
}
