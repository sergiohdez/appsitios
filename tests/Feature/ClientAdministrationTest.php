<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\ClientAdminAccount;
use App\Models\Membership;
use App\Models\AccessRequest;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_admin_can_create_users_and_sites_with_tenant_scoped_roles(): void
    {
        Notification::fake();
        [$organization, $admin] = $this->signInAsClientAdmin();

        $this->get('/')
            ->assertRedirect(route('client.dashboard'));
        $this->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee('Gestionar usuarios');
        $this->get(route('client.users.create'))->assertOk()->assertSee('Crear usuario')->assertSee('membership-submit');
        $this->withSession(['_old_input' => ['role' => 'approver']])
            ->get(route('client.users.create'))
            ->assertOk()
            ->assertSee('id="membership-submit" class="button primary" type="submit" disabled', false)
            ->assertSee('Primero crea un sitio para asignarlo a un aprobador.');
        $this->get(route('client.sites.create'))->assertOk()->assertSee('Crear sitio')->assertSee('name="timezone"', false);

        $this->post(route('client.sites.store'), [
            'name' => 'Sede Central',
            'code' => 'HQ-01',
            'address' => 'Bogotá',
            'timezone' => 'America/Bogota',
        ])->assertRedirect(route('client.sites.index'));

        $site = Site::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'client.site.created',
        ]);
        $this->post(route('client.users.store'), [
            'name' => 'Aprobadora',
            'email' => 'approver@example.test',
            'role' => 'approver',
            'site_ids' => [$site->id],
        ])->assertRedirect(route('client.users.index'));

        $this->from(route('client.users.create'))->post(route('client.users.store'), [
            'name' => 'Correo repetido',
            'email' => 'approver@example.test',
            'role' => 'requester',
        ])->assertSessionHasErrors([
            'email' => 'Ese correo electrónico ya está asociado a una cuenta. Usa otra dirección.',
        ]);

        $approver = User::query()->where('email', 'approver@example.test')->firstOrFail();
        $approverMembership = $approver->membershipFor($organization->id);
        self::assertSame(MembershipRole::APPROVER, $approverMembership->role);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'client.user.created',
            'subject_id' => $approverMembership->id,
        ]);
        self::assertTrue($approverMembership->canApprove());
        self::assertTrue($approverMembership->canRequest());
        self::assertTrue($approverMembership->approvableSites()->whereKey($site->id)->exists());
        Notification::assertSentTo($approver, SetPasswordInvitation::class);
        $this->get(route('client.users.edit', $approverMembership->id))->assertOk()->assertSee('Sitios donde puede aprobar');

        $this->post(route('client.users.store'), [
            'name' => 'Solicitante',
            'email' => 'requester@example.test',
            'role' => 'requester',
        ])->assertRedirect(route('client.users.index'));

        $requester = User::query()->where('email', 'requester@example.test')->firstOrFail();
        self::assertSame(MembershipRole::REQUESTER, $requester->membershipFor($organization->id)->role);
        self::assertFalse($requester->membershipFor($organization->id)->canApprove());
        $this->get(route('client.users.index'))->assertOk()->assertSee('approver@example.test')->assertSee('requester@example.test');
        $this->get(route('client.sites.index'))->assertOk()->assertSee('Sede Central');
        self::assertSame($admin->id, ClientAdminAccount::query()->findOrFail($organization->id)->membership->user_id);
    }

    public function test_client_admin_cannot_create_another_admin_or_access_another_tenant(): void
    {
        Notification::fake();
        [$organization] = $this->signInAsClientAdmin();
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create();
        $otherMembership = Membership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherUser->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $otherSite = Site::factory()->create(['organization_id' => $otherOrganization->id]);

        $this->post(route('client.users.store'), [
            'name' => 'Aprobador ajeno',
            'email' => 'foreign-approver@example.test',
            'role' => 'approver',
            'site_ids' => [$otherSite->id],
        ])->assertSessionHasErrors('site_ids.0');

        $this->post(route('client.users.store'), [
            'name' => 'Segundo administrador',
            'email' => 'second-admin@example.test',
            'role' => 'client_admin',
        ])->assertSessionHasErrors('role');

        $this->get(route('client.users.edit', $otherMembership->id))->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'second-admin@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'foreign-approver@example.test']);
    }

    public function test_client_admin_cannot_view_access_request_inbox(): void
    {
        [, $admin] = $this->signInAsClientAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/access-requests')->assertForbidden();
    }

    public function test_invitation_token_can_only_set_password_once(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Strong-test-password-2026',
            'password_confirmation' => 'Strong-test-password-2026',
        ])->assertRedirect(route('login'));

        self::assertNotNull($user->fresh()->email_verified_at);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Another-test-password-2026',
            'password_confirmation' => 'Another-test-password-2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_setup_returns_clear_messages_for_short_or_mismatched_passwords(): void
    {
        $user = User::factory()->create();

        $this->from(route('password.reset', ['token' => 'unused', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'unused',
                'email' => $user->email,
                'password' => 'Strong-password-2026',
                'password_confirmation' => 'Different-password-2026',
            ])
            ->assertSessionHasErrors([
                'password' => 'La confirmación de la contraseña no coincide.',
            ]);

        $this->post(route('password.update'), [
            'token' => 'unused',
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors([
            'password' => 'La contraseña debe tener al menos 12 caracteres.',
        ]);
    }

    public function test_database_enforces_at_most_one_client_admin_account_per_organization(): void
    {
        [$organization] = $this->signInAsClientAdmin();
        $secondAdmin = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $secondAdmin->id,
            'role' => MembershipRole::CLIENT_ADMIN,
        ]);

        $this->expectException(QueryException::class);
        ClientAdminAccount::query()->create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
        ]);
    }

    public function test_site_can_be_edited_deactivated_and_only_deleted_without_related_records(): void
    {
        [$organization] = $this->signInAsClientAdmin();
        $site = Site::factory()->create(['organization_id' => $organization->id]);

        $this->get(route('client.sites.edit', $site->id))->assertOk()->assertSee('Editar sitio');
        $this->patch(route('client.sites.update', $site->id), [
            'name' => 'Sitio actualizado',
            'code' => $site->code,
            'address' => 'Cali',
            'timezone' => 'America/Bogota',
            'is_active' => '0',
        ])->assertRedirect(route('client.sites.index'));
        self::assertSame('Sitio actualizado', $site->fresh()->name);
        self::assertFalse($site->fresh()->is_active);

        $this->delete(route('client.sites.destroy', $site->id))->assertRedirect(route('client.sites.index'));
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_site_cannot_be_deleted_with_an_approver_or_access_request(): void
    {
        [$organization] = $this->signInAsClientAdmin();
        $siteWithApprover = Site::factory()->create(['organization_id' => $organization->id]);
        $approver = User::factory()->create();
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($siteWithApprover->id, ['organization_id' => $organization->id]);

        $this->delete(route('client.sites.destroy', $siteWithApprover->id))
            ->assertSessionHasErrors('site');
        $this->assertDatabaseHas('sites', ['id' => $siteWithApprover->id]);

        $siteWithRequest = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $siteWithRequest->id,
            'requester_id' => $requester->id,
        ]);

        $this->delete(route('client.sites.destroy', $siteWithRequest->id))
            ->assertSessionHasErrors('site');
        $this->assertDatabaseHas('access_requests', ['id' => $accessRequest->id]);
    }

    public function test_user_cannot_be_deleted_with_pending_requests_to_create_or_approve(): void
    {
        [$organization] = $this->signInAsClientAdmin();
        $approver = User::factory()->create();
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
        ]);

        $this->get(route('client.users.index'))
            ->assertOk()
            ->assertSee('title="Resuelve primero las solicitudes pendientes"', false);

        $this->delete(route('client.users.destroy', $approverMembership->id))->assertSessionHasErrors('membership');
        $this->assertDatabaseHas('memberships', ['id' => $approverMembership->id, 'deleted_at' => null]);

        $requesterMembership = $requester->membershipFor($organization->id);
        $this->delete(route('client.users.destroy', $requesterMembership->id))->assertSessionHasErrors('membership');
        $this->assertDatabaseHas('memberships', ['id' => $requesterMembership->id, 'deleted_at' => null]);
    }

    public function test_user_without_pending_requests_is_soft_deleted_and_history_is_retained(): void
    {
        [$organization] = $this->signInAsClientAdmin();
        $requester = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => \App\Enums\AccessRequestStatus::APPROVED,
        ]);

        $this->delete(route('client.users.destroy', $membership->id))
            ->assertRedirect(route('client.users.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('memberships', ['id' => $membership->id]);
        $this->assertDatabaseHas('access_requests', ['id' => $accessRequest->id, 'requester_id' => $requester->id]);
        $this->assertDatabaseHas('users', ['id' => $requester->id, 'is_active' => false]);
        $this->get(route('client.users.index'))->assertDontSee($requester->email);
    }

    public function test_client_brand_color_is_applied_to_the_customer_area(): void
    {
        [$organization] = $this->signInAsClientAdmin();
        $organization->update(['brand_config' => ['color' => 'red']]);

        $this->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('--teal: red; --teal-dark: red;', false);
    }

    /** @return array{Organization, User} */
    private function signInAsClientAdmin(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => MembershipRole::CLIENT_ADMIN,
        ]);
        ClientAdminAccount::query()->create([
            'organization_id' => $organization->id,
            'membership_id' => $membership->id,
        ]);

        $this->actingAs($admin);

        return [$organization, $admin];
    }
}
