<?php

namespace Tests\Feature;

use App\Models\AccessRequest;
use App\Enums\MembershipRole;
use App\Models\ClientAdminAccount;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformAdminAccount;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformOrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_review_and_suspend_a_customer(): void
    {
        Notification::fake();
        $admin = $this->signInAsPlatformAdmin();
        $this->get(route('platform.organizations.create'))->assertOk()->assertSee('Administrador inicial');

        $this->post(route('platform.organizations.store'), [
            'name' => 'Cliente Alfa',
            'legal_name' => 'Cliente Alfa S.A.S.',
            'tax_id' => '9001234567',
            'billing_email' => 'billing@alfa.example',
            'contact_name' => 'Contacto Alfa',
            'contact_email' => 'contact@alfa.example',
            'timezone' => 'America/Bogota',
            'locale' => 'es',
            'brand_config' => '{"color":"red"}',
            'is_active' => '1',
            'initial_admin_name' => 'Admin Alfa',
            'initial_admin_email' => 'admin@alfa.example',
        ])->assertRedirect();

        $organization = Organization::query()->where('name', 'Cliente Alfa')->firstOrFail();
        self::assertSame(['color' => 'red'], $organization->brand_config);
        self::assertTrue($organization->is_active);
        $clientAdminAccount = ClientAdminAccount::query()->with('membership.user')->findOrFail($organization->id);
        self::assertSame(MembershipRole::CLIENT_ADMIN, $clientAdminAccount->membership->role);
        self::assertSame('admin@alfa.example', $clientAdminAccount->membership->user->email);
        Notification::assertSentTo($clientAdminAccount->membership->user, \App\Notifications\SetPasswordInvitation::class);

        $member = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $site = Site::factory()->create(['organization_id' => $organization->id, 'name' => 'Sede Alfa']);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $member->id,
        ]);

        $this->get(route('platform.organizations.show', $organization))
            ->assertOk()
            ->assertSee('Contacto Alfa')
            ->assertSee('Sede Alfa')
            ->assertSee($member->email)
            ->assertSee($accessRequest->id);

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'platform.organization.viewed',
        ]);

        $this->patch(route('platform.organizations.update', $organization), [
            'name' => 'Cliente Alfa',
            'legal_name' => 'Cliente Alfa S.A.S.',
            'tax_id' => '9001234567',
            'billing_email' => 'billing@alfa.example',
            'contact_name' => 'Contacto Alfa',
            'contact_email' => 'contact@alfa.example',
            'timezone' => 'America/Bogota',
            'locale' => 'es',
            'brand_config' => '{"color":"red"}',
            'is_active' => '0',
        ])->assertRedirect();

        self::assertFalse($organization->fresh()->is_active);
        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'event_type' => 'platform.organization.updated',
        ]);
    }

    public function test_platform_client_form_limits_timezone_locale_and_tax_id_fields(): void
    {
        $this->signInAsPlatformAdmin();
        $this->get(route('platform.organizations.create'))
            ->assertOk()
            ->assertSee('<select name="timezone"', false)
            ->assertSee('<option value="America/Bogota"', false)
            ->assertSee('<select name="locale"', false)
            ->assertSee('Español')
            ->assertSee('data-digits-only', false);

        $this->post(route('platform.organizations.store'), [
            'name' => 'Cliente NIT inválido',
            'tax_id' => '900ABC-7',
            'billing_email' => 'billing@invalid.example',
            'timezone' => 'America/Bogota',
            'locale' => 'en',
            'is_active' => '1',
            'initial_admin_name' => 'Admin inválido',
            'initial_admin_email' => 'admin-invalid@example.test',
        ])->assertSessionHasErrors(['tax_id', 'locale']);
    }

    public function test_platform_admin_cannot_open_a_request_through_another_customer_url(): void
    {
        $this->signInAsPlatformAdmin();

        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organizationA->id,
            'user_id' => $requester->id,
        ]);
        $site = Site::factory()->create(['organization_id' => $organizationA->id]);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organizationA->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
        ]);

        $this->get(route('platform.organizations.requests.show', [$organizationB, $accessRequest]))
            ->assertNotFound();
    }

    public function test_platform_admin_can_provision_an_existing_customer_without_an_administrator(): void
    {
        Notification::fake();
        $this->signInAsPlatformAdmin();
        $organization = Organization::factory()->create();

        $this->get(route('platform.organizations.show', $organization))
            ->assertOk()
            ->assertSee('Este cliente aún no tiene administrador');

        $this->post(route('platform.organizations.admin.store', $organization), [
            'initial_admin_name' => 'Admin Existente',
            'initial_admin_email' => 'admin-existing@example.test',
        ])->assertRedirect(route('platform.organizations.show', $organization));

        $adminAccount = ClientAdminAccount::query()->with('membership.user')->findOrFail($organization->id);
        self::assertSame('admin-existing@example.test', $adminAccount->membership->user->email);
        Notification::assertSentTo($adminAccount->membership->user, \App\Notifications\SetPasswordInvitation::class);

        $this->post(route('platform.organizations.admin.store', $organization), [
            'initial_admin_name' => 'Segundo Admin',
            'initial_admin_email' => 'admin-second-existing@example.test',
        ])->assertSessionHasErrors('admin');
    }

    public function test_non_platform_user_cannot_open_customer_console(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('platform.organizations.index'))->assertForbidden();
    }

    private function signInAsPlatformAdmin(): User
    {
        $admin = User::factory()->create();
        PlatformAdminAccount::query()->create([
            'singleton_id' => 1,
            'user_id' => $admin->id,
            'mfa_required' => true,
            'mfa_secret' => 'test-secret',
            'mfa_enabled_at' => now(),
            'mfa_last_used_step' => 1,
        ]);

        $this->actingAs($admin)->withSession([
            'platform_admin.mfa_verified_user_id' => $admin->id,
            'platform_admin.mfa_verified_at' => now()->timestamp,
        ]);

        return $admin;
    }
}
