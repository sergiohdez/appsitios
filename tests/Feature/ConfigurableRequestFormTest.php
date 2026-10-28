<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\ClientAdminAccount;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurableRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_admin_can_publish_additional_fields_and_requests_keep_the_selected_form_version_and_multiple_people(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $adminMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role' => MembershipRole::CLIENT_ADMIN,
        ]);
        ClientAdminAccount::query()->create([
            'organization_id' => $organization->id,
            'membership_id' => $adminMembership->id,
        ]);
        $this->actingAs($admin);

        $this->get(route('client.sites.create'))
            ->assertOk()
            ->assertSee('Campos adicionales')
            ->assertSee('Agregar campo');

        $this->post(route('client.sites.store'), [
            'name' => 'Sede con formulario propio',
            'timezone' => 'America/Bogota',
            'form_fields' => [
                ['label' => 'Código del contratista', 'type' => 'text', 'required' => '1'],
                ['label' => 'Identificación', 'type' => 'file', 'required' => '1'],
                ['label' => 'Tipo de trabajo', 'type' => 'select', 'required' => '1', 'options' => "Visita\nMantenimiento"],
            ],
        ])->assertRedirect(route('client.sites.index'));

        $site = Site::query()->where('organization_id', $organization->id)->firstOrFail();
        $firstVersion = $site->currentFormVersion()->firstOrFail();
        self::assertSame(1, $firstVersion->version);
        self::assertCount(3, $firstVersion->schema['fields']);
        $codeKey = $firstVersion->schema['fields'][0]['key'];
        $fileKey = $firstVersion->schema['fields'][1]['key'];
        $typeKey = $firstVersion->schema['fields'][2]['key'];

        $this->patch(route('client.sites.update', $site->id), [
            'name' => $site->name,
            'timezone' => 'America/Bogota',
            'is_active' => '1',
            'form_fields' => [
                ['key' => $codeKey, 'label' => 'Código de proveedor', 'type' => 'text', 'required' => '1'],
                ['key' => $fileKey, 'label' => 'Identificación', 'type' => 'file', 'required' => '1'],
                ['key' => $typeKey, 'label' => 'Tipo de trabajo', 'type' => 'select', 'required' => '1', 'options' => "Visita\nMantenimiento"],
            ],
        ])->assertRedirect(route('client.sites.index'));

        $secondVersion = $site->currentFormVersion()->firstOrFail();
        self::assertSame(2, $secondVersion->version);
        self::assertSame('Código del contratista', $firstVersion->fresh()->schema['fields'][0]['label']);
        self::assertSame('Código de proveedor', $secondVersion->schema['fields'][0]['label']);

        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        Sanctum::actingAs($requester);
        $sitesResponse = $this->getJson('/api/v1/sites')->assertOk();
        $sitesResponse->assertJsonPath('data.0.form_version_id', $secondVersion->id);
        $sitesResponse->assertJsonPath('data.0.form_schema.fields.0.label', 'Código de proveedor');

        Storage::fake('private');
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/access-requests', [
            'site_id' => $site->id,
            'form_version_id' => $secondVersion->id,
            'purpose' => 'Mantenimiento programado',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => [
                $codeKey => 'PROV-204',
                $fileKey => UploadedFile::fake()->createWithContent('identificacion.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
                $typeKey => 'Mantenimiento',
            ],
            'people' => [
                ['full_name' => 'Ana Persona', 'document_type' => 'CC', 'document_number' => '1001'],
                ['full_name' => 'Luis Persona', 'document_type' => 'CC', 'document_number' => '1002'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.form_version.version', 2)
            ->assertJsonPath("data.form_data.{$codeKey}", 'PROV-204')
            ->assertJsonPath("data.form_data.{$typeKey}", 'Mantenimiento')
            ->assertJsonCount(2, 'data.people');

        $accessRequest = AccessRequest::query()->firstOrFail();
        self::assertSame($secondVersion->id, $accessRequest->form_version_id);
        self::assertSame(2, $accessRequest->people()->count());
        $this->assertDatabaseHas('access_request_attachments', [
            'access_request_id' => $accessRequest->id,
            'form_field_key' => $fileKey,
            'original_name' => 'identificacion.pdf',
        ]);

        $this->actingAs($admin)->patch(route('client.sites.update', $site->id), [
            'name' => $site->name,
            'timezone' => 'America/Bogota',
            'is_active' => '1',
            'form_fields' => [
                ['key' => $codeKey, 'label' => 'Código interno', 'type' => 'text', 'required' => '1'],
                ['key' => $fileKey, 'label' => 'Identificación', 'type' => 'file', 'required' => '1'],
                ['key' => $typeKey, 'label' => 'Tipo de trabajo', 'type' => 'select', 'required' => '1', 'options' => "Visita\nMantenimiento"],
            ],
        ])->assertRedirect(route('client.sites.index'));

        self::assertSame(3, $site->currentFormVersion()->firstOrFail()->version);
        self::assertSame('Código de proveedor', $accessRequest->fresh()->formVersion->schema['fields'][0]['label']);
    }

    public function test_server_rejects_missing_required_custom_fields_and_requests_without_people(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $version = $site->formVersions()->create([
            'organization_id' => $organization->id,
            'version' => 1,
            'schema' => ['fields' => [[
                'key' => 'field_category01',
                'label' => 'Categoría',
                'type' => 'select',
                'required' => true,
                'options' => ['Visita', 'Mantenimiento'],
            ]]],
            'published_at' => now(),
        ]);
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        Sanctum::actingAs($requester);

        $this->postJson('/api/v1/access-requests', [
            'site_id' => $site->id,
            'form_version_id' => $version->id,
            'purpose' => 'Ingreso de prueba',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => ['field_category01' => 'Una opción que no existe'],
            'people' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['form_data.field_category01', 'people']);

        $site->formVersions()->create([
            'organization_id' => $organization->id,
            'version' => 2,
            'schema' => $version->schema,
            'published_at' => now(),
        ]);

        $this->postJson('/api/v1/access-requests', [
            'site_id' => $site->id,
            'form_version_id' => $version->id,
            'purpose' => 'Ingreso de prueba',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => ['field_category01' => 'Visita'],
            'people' => [['full_name' => 'Persona de prueba']],
        ])->assertUnprocessable()->assertJsonValidationErrors('form_version_id');

        $this->assertDatabaseCount('access_requests', 0);
    }
}
