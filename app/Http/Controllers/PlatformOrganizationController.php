<?php

namespace App\Http\Controllers;

use App\Models\AccessRequest;
use App\Models\Organization;
use App\Services\PlatformAdminAudit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformOrganizationController extends Controller
{
    public function index(Request $request, PlatformAdminAudit $audit): ViewContract
    {
        $query = Organization::query()->withCount(['memberships', 'sites', 'accessRequests']);
        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('legal_name', 'like', '%'.$search.'%')
                    ->orWhere('tax_id', 'like', '%'.$search.'%')
                    ->orWhere('billing_email', 'like', '%'.$search.'%');
            });
        }

        match ($request->query('status')) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        $organizations = $query->orderBy('name')->paginate(15)->withQueryString();

        foreach ($organizations as $organization) {
            $audit->record($organization, 'platform.organization.listed', Organization::class, $organization->id);
        }

        return view('platform.organizations.index', compact('organizations', 'search'));
    }

    public function create(): ViewContract
    {
        return view('platform.organizations.form', ['organization' => new Organization()]);
    }

    public function store(Request $request, PlatformAdminAudit $audit): RedirectResponse
    {
        $data = $this->validatedOrganization($request);

        $organization = DB::transaction(function () use ($data, $audit): Organization {
            $organization = Organization::query()->create($data);
            $audit->record($organization, 'platform.organization.created', Organization::class, $organization->id, [
                'changed_fields' => array_keys($data),
            ]);

            return $organization;
        });

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', 'Cliente creado.');
    }

    public function show(Organization $organization, PlatformAdminAudit $audit): ViewContract
    {
        $audit->record($organization, 'platform.organization.viewed', Organization::class, $organization->id);
        $organization->loadCount(['memberships', 'sites', 'accessRequests']);

        $memberships = $organization->memberships()
            ->with('user:id,name,email,is_active')
            ->orderBy('created_at')
            ->paginate(10, ['*'], 'members_page')
            ->withQueryString();
        $sites = $organization->sites()
            ->withCount('accessRequests')
            ->orderBy('name')
            ->paginate(10, ['*'], 'sites_page')
            ->withQueryString();
        $requests = $organization->accessRequests()
            ->with(['site:id,name', 'requester:id,name,email', 'people:id,access_request_id,full_name,company'])
            ->latest()
            ->paginate(10, ['*'], 'requests_page')
            ->withQueryString();

        return view('platform.organizations.show', compact('organization', 'memberships', 'sites', 'requests'));
    }

    public function edit(Organization $organization, PlatformAdminAudit $audit): ViewContract
    {
        $audit->record($organization, 'platform.organization.edit_form_viewed', Organization::class, $organization->id);

        return view('platform.organizations.form', compact('organization'));
    }

    public function update(Request $request, Organization $organization, PlatformAdminAudit $audit): RedirectResponse
    {
        $data = $this->validatedOrganization($request);
        $before = $organization->getAttributes();

        DB::transaction(function () use ($organization, $data, $before, $audit): void {
            $organization->fill($data)->save();
            $changedFields = [];

            foreach (array_keys($data) as $field) {
                if (($before[$field] ?? null) !== $organization->getAttributes()[$field]) {
                    $changedFields[] = $field;
                }
            }

            $audit->record($organization, 'platform.organization.updated', Organization::class, $organization->id, [
                'changed_fields' => $changedFields,
            ]);
        });

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', 'Cambios guardados.');
    }

    private function validatedOrganization(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'billing_email' => ['required', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'string', 'max:12'],
            'brand_config' => ['nullable', 'string', 'json', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $brandConfig = trim((string) ($data['brand_config'] ?? ''));
        $data['brand_config'] = null;

        if ($brandConfig !== '') {
            try {
                $decoded = json_decode($brandConfig, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['brand_config' => 'La configuración debe contener un objeto JSON válido.']);
            }

            if (! is_array($decoded) || ! str_starts_with(ltrim($brandConfig), '{')) {
                throw ValidationException::withMessages(['brand_config' => 'La configuración debe ser un objeto JSON, por ejemplo {"color":"#087f83"}.']);
            }

            $data['brand_config'] = $decoded;
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
