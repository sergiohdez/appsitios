<?php

namespace App\Http\Controllers\Client;

use App\Enums\MembershipRole;
use App\Enums\AccessRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use App\Services\SendPasswordSetupInvitation;
use App\Services\ClientAdminAudit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientUserController extends Controller
{
    public function index(Request $request): ViewContract
    {
        $tenant = $request->attributes->get('tenant');
        $memberships = $tenant->memberships()
            ->whereIn('role', [MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])
            ->with(['user:id,name,email,email_verified_at,is_active', 'approvableSites:id,name'])
            ->withCount([
                'createdAccessRequests as pending_requests_count' => fn ($query) => $query->where('status', AccessRequestStatus::PENDING->value),
                'approvableSites as pending_approvals_count' => fn ($query) => $query->whereHas(
                    'accessRequests',
                    fn ($requests) => $requests->where('status', AccessRequestStatus::PENDING->value),
                ),
            ])
            ->orderBy('created_at')
            ->paginate(15);

        return view('client.users.index', compact('memberships'));
    }

    public function create(Request $request): ViewContract
    {
        $sites = $request->attributes->get('tenant')->sites()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('client.users.form', [
            'membership' => new Membership(),
            'sites' => $sites,
            'editing' => false,
        ]);
    }

    public function store(Request $request, SendPasswordSetupInvitation $invitations, ClientAdminAudit $audit): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $this->validatedUser($request, $tenant->id);

        [$user, $membership] = DB::transaction(function () use ($data, $tenant, $audit): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
            ]);
            $membership = $tenant->memberships()->create([
                'user_id' => $user->id,
                'role' => MembershipRole::from($data['role']),
                'is_active' => true,
            ]);

            if ($membership->canApprove()) {
                $membership->approvableSites()->sync(collect($data['site_ids'])->mapWithKeys(
                    fn (string $siteId): array => [$siteId => ['organization_id' => $tenant->id]]
                )->all());
            }

            $audit->record($tenant, 'client.user.created', Membership::class, $membership->id, [
                'role' => $membership->role->value,
            ]);

            return [$user, $membership];
        });

        $sent = $invitations->send($user);
        $audit->record($tenant, 'client.user.access_link_sent', User::class, (string) $user->id, ['sent' => $sent]);

        return redirect()->route('client.users.index')
            ->with($sent ? 'status' : 'warning', $sent
                ? "Usuario creado. Se envió un enlace para activar {$user->email}."
                : "Usuario creado, pero no se pudo enviar el correo a {$user->email}. Puedes reenviar el enlace desde la lista.");
    }

    public function edit(Request $request, string $membershipId): ViewContract
    {
        $tenant = $request->attributes->get('tenant');
        $membership = $tenant->memberships()
            ->whereIn('role', [MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])
            ->with(['user:id,name,email,is_active', 'approvableSites:id,name'])
            ->findOrFail($membershipId);
        $sites = $tenant->sites()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('client.users.form', [
            'membership' => $membership,
            'sites' => $sites,
            'editing' => true,
            'selectedSiteIds' => $membership->approvableSites->modelKeys(),
        ]);
    }

    public function update(Request $request, string $membershipId, ClientAdminAudit $audit): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $membership = $tenant->memberships()
            ->whereIn('role', [MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])
            ->findOrFail($membershipId);
        $data = $this->validatedUser($request, $tenant->id);

        $beforeRole = $membership->role->value;
        DB::transaction(function () use ($membership, $data, $tenant, $audit, $beforeRole): void {
            $membership->update([
                'role' => MembershipRole::from($data['role']),
                'is_active' => (bool) $data['is_active'],
            ]);

            $siteIds = $membership->canApprove() ? ($data['site_ids'] ?? []) : [];
            $membership->approvableSites()->sync(collect($siteIds)->mapWithKeys(
                fn (string $siteId): array => [$siteId => ['organization_id' => $tenant->id]]
            )->all());
            $audit->record($tenant, 'client.user.updated', Membership::class, $membership->id, [
                'previous_role' => $beforeRole,
                'role' => $membership->role->value,
                'is_active' => $membership->is_active,
                'approvable_site_ids' => array_values($siteIds),
            ]);
        });

        return redirect()->route('client.users.index')->with('status', 'Cambios de usuario guardados.');
    }

    public function resendInvitation(
        Request $request,
        string $membershipId,
        SendPasswordSetupInvitation $invitations,
        ClientAdminAudit $audit,
    ): RedirectResponse {
        $tenant = $request->attributes->get('tenant');
        $membership = $tenant->memberships()
            ->whereIn('role', [MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])
            ->with('user')
            ->findOrFail($membershipId);
        $sent = $invitations->send($membership->user);
        $audit->record($tenant, 'client.user.access_link_sent', User::class, (string) $membership->user->id, ['sent' => $sent]);

        return redirect()->route('client.users.index')
            ->with($sent ? 'status' : 'warning', $sent
                ? "Se envió un enlace seguro a {$membership->user->email}."
                : 'No se pudo enviar el correo. Revisa la configuración de correo saliente e inténtalo de nuevo.');
    }

    public function destroy(Request $request, string $membershipId, ClientAdminAudit $audit): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');

        return DB::transaction(function () use ($tenant, $membershipId, $audit): RedirectResponse {
            $membership = $tenant->memberships()
                ->whereIn('role', [MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($membershipId);

            $hasPendingRequests = $tenant->accessRequests()
                ->where('requester_id', $membership->user_id)
                ->where('status', AccessRequestStatus::PENDING->value)
                ->exists();
            $hasPendingApprovals = $membership->approvableSites()
                ->whereHas('accessRequests', fn ($query) => $query->where('status', AccessRequestStatus::PENDING->value))
                ->exists();

            if ($hasPendingRequests || $hasPendingApprovals) {
                throw ValidationException::withMessages([
                    'membership' => 'No se puede eliminar este usuario mientras tenga solicitudes pendientes propias o asignadas para aprobar.',
                ]);
            }

            $user = $membership->user;
            $membership->approvableSites()->detach();
            $membership->update(['is_active' => false]);
            $membership->delete();

            if (! $user->memberships()->where('is_active', true)->exists() && ! $user->platformAdminAccount()->exists()) {
                $user->forceFill(['is_active' => false])->save();
            }

            $audit->record($tenant, 'client.user.deleted', Membership::class, $membership->id, [
                'role' => $membership->role->value,
            ]);

            return redirect()->route('client.users.index')->with('status', 'Usuario eliminado. Se conservaron sus solicitudes y el historial de auditoría.');
        });
    }

    private function validatedUser(Request $request, string $tenantId): array
    {
        $isEditing = $request->routeIs('client.users.update');

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [$isEditing ? 'sometimes' : 'required', 'email', 'max:255', $isEditing ? 'prohibited' : Rule::unique('users', 'email')],
            'role' => ['required', Rule::in([MembershipRole::APPROVER->value, MembershipRole::REQUESTER->value])],
            'is_active' => [$isEditing ? 'required' : 'prohibited', 'boolean'],
            'site_ids' => [Rule::requiredIf(fn (): bool => $request->input('role') === MembershipRole::APPROVER->value), 'array', Rule::prohibitedIf(fn (): bool => $request->input('role') === MembershipRole::REQUESTER->value)],
            'site_ids.*' => ['required', 'ulid', Rule::exists('sites', 'id')->where('organization_id', $tenantId)->where('is_active', true)],
        ], [
            'email.unique' => 'Ese correo electrónico ya está asociado a una cuenta. Usa otra dirección.',
        ]);
    }
}
