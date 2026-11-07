<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\MembershipRole;
use App\Models\AccessPerson;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationFormVersion;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessRequestDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_returns_form_data_and_decider(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create(['name' => 'Solicitante Form']);
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $approver = User::factory()->create(['name' => 'Aprobador Form']);
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        $textKey = 'field_'.str_repeat('b', 8);
        $version = OrganizationFormVersion::query()->create([
            'organization_id' => $organization->id,
            'version' => 1,
            'schema' => ['fields' => [
                ['key' => $textKey, 'label' => 'Código contratista', 'type' => 'text', 'required' => true, 'max_length' => 2000],
            ]],
        ]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'purpose' => 'Mantenimiento preventivo',
            'requested_from' => now()->addDays(2),
            'requested_until' => now()->addDays(3),
            'organization_form_version_id' => $version->id,
            'form_data' => [$textKey => 'PROV-0042'],
            'decided_by' => $approver->id,
            'decided_at' => now()->subHour(),
            'decision_comment' => 'Aprobada con escolta.',
        ]);

        Sanctum::actingAs($requester);

        $this->getJson(route('api.access-requests.show', $accessRequest->id))
            ->assertOk()
            ->assertJsonPath('data.form_data.'.$textKey, 'PROV-0042')
            ->assertJsonPath('data.decided_by.name', 'Aprobador Form')
            ->assertJsonPath('data.decision_comment', 'Aprobada con escolta.')
            ->assertJsonPath('data.decided_at', $accessRequest->fresh()->decided_at->toJSON());
    }

    public function test_requester_sees_full_details_of_own_request_including_people_and_attachments(): void
    {
        Storage::fake('private');
        $organization = Organization::factory()->create(['name' => 'Cliente Detalle']);
        $site = Site::factory()->create(['organization_id' => $organization->id, 'name' => 'Sede Detalle']);
        $requester = User::factory()->create(['name' => 'Solicitante Detalle', 'email' => 'solicitante@example.test']);
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);

        $fileKey = 'field_'.str_repeat('a', 8);
        $version = OrganizationFormVersion::query()->create([
            'organization_id' => $organization->id,
            'version' => 1,
            'schema' => ['fields' => [
                ['key' => $fileKey, 'label' => 'Identificación', 'type' => 'file', 'required' => false, 'extensions' => ['pdf']],
            ]],
        ]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::APPROVED,
            'purpose' => 'Mantenimiento preventivo',
            'requested_from' => now()->addDays(2),
            'requested_until' => now()->addDays(3),
            'organization_form_version_id' => $version->id,
            'form_data' => [],
            'decided_at' => now()->subHour(),
        ]);
        $accessRequest->people()->create([
            'full_name' => 'Visitante Detalle',
            'document_type' => 'CC',
            'document_number' => 'DOC-DETALLE-1',
            'company' => 'Proveedor Detalle',
        ]);
        Storage::disk('private')->put(
            "tenants/{$organization->id}/requests/{$accessRequest->id}/detalle.pdf",
            '%PDF-detalle',
        );
        $attachment = $accessRequest->attachments()->create([
            'storage_path' => "tenants/{$organization->id}/requests/{$accessRequest->id}/detalle.pdf",
            'original_name' => 'detalle.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'uploaded_by' => $requester->id,
            'form_field_key' => $fileKey,
        ]);

        Sanctum::actingAs($requester);

        $response = $this->getJson(route('api.access-requests.show', $accessRequest->id))
            ->assertOk()
            ->assertJsonPath('data.site.name', 'Sede Detalle')
            ->assertJsonPath('data.requester.name', 'Solicitante Detalle')
            ->assertJsonPath('data.requester.email', 'solicitante@example.test')
            ->assertJsonPath('data.purpose', 'Mantenimiento preventivo')
            ->assertJsonPath('data.people.0.full_name', 'Visitante Detalle')
            ->assertJsonPath('data.people.0.document_type', 'CC')
            ->assertJsonPath('data.people.0.document_number', 'DOC-DETALLE-1')
            ->assertJsonPath('data.people.0.company', 'Proveedor Detalle')
            ->assertJsonPath('data.attachments.0.original_name', 'detalle.pdf')
            ->assertJsonPath('data.attachments.0.form_field_key', $fileKey)
            ->assertJsonPath('data.attachments.0.download_url', route('api.access-requests.attachments.show', [$accessRequest->id, $attachment->id]))
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.can_decide', false);

        // La ruta privada del almacenamiento no debe filtrarse.
        $this->assertStringNotContainsString('storage_path', json_encode($response->json('data')));
        $this->assertStringNotContainsString("tenants/{$organization->id}", json_encode($response->json('data')));
    }

    public function test_assigned_approver_sees_details_of_others_request(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create(['name' => 'Solicitante Original']);
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $approver = User::factory()->create(['name' => 'Aprobador Detalle']);
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $accessRequest->people()->create([
            'full_name' => 'Visitante Aprobador',
            'document_type' => 'PP',
            'document_number' => 'PP-123',
            'company' => 'Empresa X',
        ]);

        Sanctum::actingAs($approver);

        $this->getJson(route('api.access-requests.show', $accessRequest->id))
            ->assertOk()
            ->assertJsonPath('data.requester.name', 'Solicitante Original')
            ->assertJsonPath('data.people.0.full_name', 'Visitante Aprobador')
            ->assertJsonPath('data.people.0.document_number', 'PP-123')
            ->assertJsonPath('data.can_decide', true)
            ->assertJsonPath('data.can_cancel', true);
    }

    public function test_unassigned_user_cannot_see_details(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $otherRequester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $otherRequester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $unassignedApprover = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $unassignedApprover->id,
            'role' => MembershipRole::APPROVER,
        ]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($otherRequester);
        $this->getJson(route('api.access-requests.show', $accessRequest->id))->assertForbidden();

        Sanctum::actingAs($unassignedApprover);
        $this->getJson(route('api.access-requests.show', $accessRequest->id))->assertForbidden();
    }

    public function test_cannot_see_details_from_another_tenant(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);

        $otherOrganization = Organization::factory()->create();
        $otherSite = Site::factory()->create(['organization_id' => $otherOrganization->id]);
        $otherRequester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherRequester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $foreign = AccessRequest::factory()->create([
            'organization_id' => $otherOrganization->id,
            'site_id' => $otherSite->id,
            'requester_id' => $otherRequester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($requester);

        $this->getJson(route('api.access-requests.show', $foreign->id))->assertNotFound();
    }

    public function test_creator_and_assigned_approver_can_download_attachment(): void
    {
        Storage::fake('private');
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $approver = User::factory()->create();
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $content = '%PDF-descarga-detalle';
        $storagePath = "tenants/{$organization->id}/requests/{$accessRequest->id}/descarga.pdf";
        Storage::disk('private')->put($storagePath, $content);
        $attachment = $accessRequest->attachments()->create([
            'storage_path' => $storagePath,
            'original_name' => 'descarga.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'uploaded_by' => $requester->id,
        ]);

        Sanctum::actingAs($requester);
        $created = $this->get(route('api.access-requests.attachments.show', [$accessRequest->id, $attachment->id]))->assertOk();
        self::assertSame($content, $created->streamedContent());
        $created->assertHeader('Content-Type', 'application/pdf');

        Sanctum::actingAs($approver);
        $downloaded = $this->get(route('api.access-requests.attachments.show', [$accessRequest->id, $attachment->id]))->assertOk();
        self::assertSame($content, $downloaded->streamedContent());

        $this->assertDatabaseHas('audit_events', [
            'organization_id' => $organization->id,
            'actor_user_id' => $approver->id,
            'event_type' => 'access_request.attachment_downloaded',
            'subject_id' => $attachment->id,
        ]);
    }

    public function test_unauthorized_user_cannot_download_attachment(): void
    {
        Storage::fake('private');
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $intruder = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $intruder->id,
            'role' => MembershipRole::REQUESTER,
        ]);

        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);
        $storagePath = "tenants/{$organization->id}/requests/{$accessRequest->id}/privado.pdf";
        Storage::disk('private')->put($storagePath, '%PDF-privado');
        $attachment = $accessRequest->attachments()->create([
            'storage_path' => $storagePath,
            'original_name' => 'privado.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'uploaded_by' => $requester->id,
        ]);

        Sanctum::actingAs($intruder);

        $this->get(route('api.access-requests.attachments.show', [$accessRequest->id, $attachment->id]))
            ->assertForbidden();
    }

    public function test_missing_attachment_returns_not_found(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $accessRequest = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
            'status' => AccessRequestStatus::PENDING,
            'requested_from' => now()->addDay(),
            'requested_until' => now()->addDays(2),
        ]);

        Sanctum::actingAs($requester);

        $fakeId = \Illuminate\Support\Str::ulid();
        $this->get(route('api.access-requests.attachments.show', [$accessRequest->id, $fakeId]))
            ->assertNotFound();
    }
}
