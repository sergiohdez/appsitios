<?php

namespace App\Http\Controllers;

use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\ClientAdminAccount;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\PlatformAdminAudit;
use App\Services\SendPasswordSetupInvitation;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, PlatformAdminAudit $audit, SendPasswordSetupInvitation $invitations): RedirectResponse
    {
        $data = $this->validatedOrganization($request);
        $request->merge([
            'initial_admin_email' => mb_strtolower(trim((string) $request->input('initial_admin_email'))),
        ]);
        $adminData = $request->validate([
            'initial_admin_name' => ['required', 'string', 'max:255'],
            'initial_admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        [$organization, $adminUser] = DB::transaction(function () use ($data, $adminData, $audit): array {
            $organization = Organization::query()->create($data);
            $adminUser = $this->createClientAdmin($organization, $adminData['initial_admin_name'], $adminData['initial_admin_email']);

            $audit->record($organization, 'platform.organization.created', Organization::class, $organization->id, [
                'changed_fields' => array_keys($data),
                'initial_admin_user_id' => $adminUser->id,
            ]);

            return [$organization, $adminUser];
        });

        $inviteSent = $invitations->send($adminUser);

        return redirect()->route('platform.organizations.show', $organization)
            ->with($inviteSent ? 'status' : 'warning', $inviteSent
                ? 'Cliente y administrador creados. Se envió la invitación por correo.'
                : 'Cliente y administrador creados, pero no se pudo enviar el correo. Revisa el correo saliente y reenvía la invitación desde esta página.');
    }

    public function createAdmin(
        Request $request,
        Organization $organization,
        PlatformAdminAudit $audit,
        SendPasswordSetupInvitation $invitations,
    ): RedirectResponse {
        $request->merge(['initial_admin_email' => mb_strtolower(trim((string) $request->input('initial_admin_email')))]);
        $data = $request->validate([
            'initial_admin_name' => ['required', 'string', 'max:255'],
            'initial_admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $adminUser = DB::transaction(function () use ($organization, $data, $audit): User {
            $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrganization->clientAdminAccount()->exists()) {
                throw ValidationException::withMessages(['admin' => 'Este cliente ya tiene un administrador asignado.']);
            }

            $adminUser = $this->createClientAdmin($lockedOrganization, $data['initial_admin_name'], $data['initial_admin_email']);
            $audit->record($lockedOrganization, 'platform.organization.admin_created', User::class, (string) $adminUser->id);

            return $adminUser;
        });

        $sent = $invitations->send($adminUser);

        return redirect()->route('platform.organizations.show', $organization)
            ->with($sent ? 'status' : 'warning', $sent
                ? 'Se creó el administrador del cliente y se envió su invitación por correo.'
                : 'Se creó el administrador del cliente, pero no se pudo enviar el correo. Revisa el correo saliente e inténtalo de nuevo.');
    }

    public function show(Organization $organization, PlatformAdminAudit $audit): ViewContract
    {
        $audit->record($organization, 'platform.organization.viewed', Organization::class, $organization->id);
        $organization->loadCount(['memberships', 'sites', 'accessRequests']);
        $organization->load('clientAdminAccount.membership.user');

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

        $adminMembership = $organization->clientAdminAccount?->membership;

        return view('platform.organizations.show', compact('organization', 'memberships', 'sites', 'requests', 'adminMembership'));
    }

    public function resendAdminInvitation(
        Organization $organization,
        SendPasswordSetupInvitation $invitations,
        PlatformAdminAudit $audit,
    ): RedirectResponse {
        $admin = $organization->clientAdminAccount()->with('membership.user')->firstOrFail();
        $sent = $invitations->send($admin->membership->user);
        $audit->record($organization, 'platform.organization.admin_access_link_sent', User::class, (string) $admin->membership->user->id, [
            'sent' => $sent,
        ]);

        return redirect()->route('platform.organizations.show', $organization)
            ->with($sent ? 'status' : 'warning', $sent
                ? 'Se envió un enlace seguro al correo del administrador del cliente.'
                : 'No se pudo enviar el correo. Revisa la configuración de correo saliente e inténtalo de nuevo.');
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
            'tax_id' => ['nullable', 'string', 'max:80', 'regex:/^[0-9]+$/'],
            'billing_email' => ['required', 'email', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(['es'])],
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

            if (array_key_exists('color', $decoded) && (! is_string($decoded['color']) || ! preg_match('/^(?:#[a-fA-F0-9]{3,8}|[a-zA-Z]+)$/D', $decoded['color']))) {
                throw ValidationException::withMessages(['brand_config' => 'El campo color debe ser un nombre de color CSS o un valor hexadecimal, por ejemplo red o #087f83.']);
            }

            $data['brand_config'] = $decoded;
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function createClientAdmin(Organization $organization, string $name, string $email): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(64),
        ]);
        $membership = Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => MembershipRole::CLIENT_ADMIN,
            'is_active' => true,
        ]);
        ClientAdminAccount::query()->create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
        ]);

        return $user;
    }
}
