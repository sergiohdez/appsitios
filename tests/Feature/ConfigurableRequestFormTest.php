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

    public function test_client_admin_configures_one_form_used_by_requests_for_every_site(): void
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
            ->assertDontSee('Campos adicionales');
        $this->get(route('client.request-form.edit'))
            ->assertOk()
            ->assertSee('Formulario')
            ->assertSee('Campos adicionales')
            ->assertSee('todos los sitios');

        foreach (['Sede Norte', 'Sede Sur'] as $name) {
            $this->post(route('client.sites.store'), [
                'name' => $name,
                'timezone' => 'America/Bogota',
            ])->assertRedirect(route('client.sites.index'));
        }

        $sites = Site::query()->where('organization_id', $organization->id)->orderBy('name')->get();
        self::assertCount(2, $sites);

        $this->patch(route('client.request-form.update'), [
            'form_fields' => [
                ['label' => 'Código del contratista', 'type' => 'text', 'required' => '1', 'max_length' => '20', 'default' => 'PROV-'],
                ['label' => 'Identificación', 'type' => 'file', 'required' => '1', 'extensions' => ['pdf']],
                ['label' => 'Tipo de trabajo', 'type' => 'select', 'required' => '1', 'options' => "Visita\nMantenimiento", 'default' => 'Mantenimiento'],
                ['label' => 'Fecha de visita', 'type' => 'date', 'date_format' => 'd/m/Y', 'default' => '31/12/2026'],
            ],
        ])->assertRedirect(route('client.request-form.edit'));

        $firstVersion = $organization->requestFormVersions()->orderByDesc('version')->firstOrFail();
        self::assertSame(1, $firstVersion->version);
        self::assertCount(4, $firstVersion->schema['fields']);
        self::assertSame(20, $firstVersion->schema['fields'][0]['max_length']);
        self::assertSame('PROV-', $firstVersion->schema['fields'][0]['default']);
        self::assertSame(['pdf'], $firstVersion->schema['fields'][1]['extensions']);
        self::assertSame('Mantenimiento', $firstVersion->schema['fields'][2]['default']);
        $codeKey = $firstVersion->schema['fields'][0]['key'];
        $fileKey = $firstVersion->schema['fields'][1]['key'];
        $typeKey = $firstVersion->schema['fields'][2]['key'];
        $dateKey = $firstVersion->schema['fields'][3]['key'];
        self::assertSame('d/m/Y', $firstVersion->schema['fields'][3]['date_format']);
        self::assertSame('31/12/2026', $firstVersion->schema['fields'][3]['default']);

        $formPage = $this->get(route('client.request-form.edit'))->assertOk();
        $formPage->assertSee('Máximo de caracteres')
            ->assertSee('Máximo de líneas')
            ->assertSee('Formato de fecha')
            ->assertSee('Extensiones permitidas')
            ->assertSee('id="request-form-builder"', false)
            ->assertSee('placeholder="DD/MM/AAAA"', false)
            ->assertSee('Formato: DD/MM/AAAA')
            ->assertSee('XLS')
            ->assertSee('XLSX')
            ->assertSee('TXT')
            ->assertSee('CSV')
            ->assertSee('data-setting="select"', false)
            ->assertSee('Opciones (una por línea)');
        self::assertMatchesRegularExpression(
            '/data-setting="select"[^>]*\shidden(?:\s|>)/',
            $formPage->getContent(),
        );

        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        Sanctum::actingAs($requester);

        $sitesResponse = $this->getJson('/api/v1/sites')->assertOk();
        $sitesResponse->assertJsonPath('data.0.organization_form_version_id', $firstVersion->id)
            ->assertJsonPath('data.1.organization_form_version_id', $firstVersion->id)
            ->assertJsonPath('data.0.form_schema.fields.0.label', 'Código del contratista')
            ->assertJsonPath('data.1.form_schema.fields.0.label', 'Código del contratista');

        Storage::fake('private');
        $createdRequests = [];
        foreach ($sites as $index => $site) {
            $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/access-requests', [
                'site_id' => $site->id,
                'organization_form_version_id' => $firstVersion->id,
                'purpose' => 'Mantenimiento programado',
                'requested_from' => now()->addDay()->toIso8601String(),
                'requested_until' => now()->addDays(2)->toIso8601String(),
                'form_data' => [
                    $codeKey => 'PROV-20'.($index + 4),
                    $fileKey => UploadedFile::fake()->createWithContent('identificacion.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"),
                    $typeKey => 'Mantenimiento',
                    $dateKey => '31/12/2026',
                ],
                'people' => [
                    ['full_name' => 'Ana Persona', 'document_type' => 'CC', 'document_number' => '1001'],
                    ['full_name' => 'Luis Persona', 'document_type' => 'CC', 'document_number' => '1002'],
                ],
            ]);

            $response->assertCreated()
                ->assertJsonPath('data.organization_form_version.version', 1)
                ->assertJsonPath("data.form_data.{$codeKey}", 'PROV-20'.($index + 4))
                ->assertJsonPath("data.form_data.{$typeKey}", 'Mantenimiento')
                ->assertJsonCount(2, 'data.people');
            $createdRequests[] = AccessRequest::query()->findOrFail($response->json('data.id'));
        }

        foreach ($createdRequests as $accessRequest) {
            self::assertSame($firstVersion->id, $accessRequest->organization_form_version_id);
            self::assertNull($accessRequest->form_version_id);
            self::assertSame(2, $accessRequest->people()->count());
            $this->assertDatabaseHas('access_request_attachments', [
                'access_request_id' => $accessRequest->id,
                'form_field_key' => $fileKey,
                'original_name' => 'identificacion.pdf',
            ]);
        }

        $this->actingAs($admin)->patch(route('client.request-form.update'), [
            'form_fields' => [
                ['key' => $codeKey, 'label' => 'Código de proveedor', 'type' => 'text', 'required' => '1', 'max_length' => '20', 'default' => 'PROV-'],
                ['key' => $fileKey, 'label' => 'Identificación', 'type' => 'file', 'required' => '1', 'extensions' => ['pdf']],
                ['key' => $typeKey, 'label' => 'Tipo de trabajo', 'type' => 'select', 'required' => '1', 'options' => "Visita\nMantenimiento", 'default' => 'Mantenimiento'],
                ['key' => $dateKey, 'label' => 'Fecha de visita', 'type' => 'date', 'date_format' => 'd/m/Y', 'default' => '31/12/2026'],
            ],
        ])->assertRedirect(route('client.request-form.edit'));

        $secondVersion = $organization->requestFormVersions()->orderByDesc('version')->firstOrFail();
        self::assertSame(2, $secondVersion->version);
        self::assertSame('Código del contratista', $firstVersion->fresh()->schema['fields'][0]['label']);
        self::assertSame('Código de proveedor', $secondVersion->schema['fields'][0]['label']);
        self::assertSame('Código del contratista', $createdRequests[0]->fresh()->organizationFormVersion->schema['fields'][0]['label']);
    }

    public function test_server_validates_custom_fields_against_the_current_client_form_and_requires_a_person(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $version = $organization->requestFormVersions()->create([
            'version' => 1,
            'schema' => ['fields' => [[
                'key' => 'field_category01',
                'label' => 'Categoría',
                'type' => 'select',
                'required' => true,
                'options' => ['Visita', 'Mantenimiento'],
            ], [
                'key' => 'field_code0001',
                'label' => 'Código',
                'type' => 'text',
                'max_length' => 3,
                'required' => true,
            ], [
                'key' => 'field_notes0001',
                'label' => 'Notas',
                'type' => 'textarea',
                'max_lines' => 2,
                'required' => true,
            ], [
                'key' => 'field_date0001',
                'label' => 'Fecha de visita',
                'type' => 'date',
                'date_format' => 'd/m/Y',
                'required' => true,
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
            'organization_form_version_id' => $version->id,
            'purpose' => 'Ingreso de prueba',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => [
                'field_category01' => 'Una opción que no existe',
                'field_code0001' => 'ABCD',
                'field_notes0001' => "Línea 1\nLínea 2\nLínea 3",
                'field_date0001' => '31/02/2026',
            ],
            'people' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'form_data.field_category01',
            'form_data.field_code0001',
            'form_data.field_notes0001',
            'form_data.field_date0001',
            'people',
        ]);

        $organization->requestFormVersions()->create([
            'version' => 2,
            'schema' => $version->schema,
            'published_at' => now(),
        ]);

        $this->postJson('/api/v1/access-requests', [
            'site_id' => $site->id,
            'organization_form_version_id' => $version->id,
            'purpose' => 'Ingreso de prueba',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => [
                'field_category01' => 'Visita',
                'field_code0001' => 'ABC',
                'field_notes0001' => "Línea 1\nLínea 2",
                'field_date0001' => '28/02/2026',
            ],
            'people' => [['full_name' => 'Persona de prueba']],
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_form_version_id');

        $this->postJson('/api/v1/access-requests', [
            'site_id' => $site->id,
            'organization_form_version_id' => $organization->requestFormVersions()->orderByDesc('version')->value('id'),
            'purpose' => 'Ingreso de prueba',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => [
                'field_category01' => 'Visita',
                'field_code0001' => 'ABC',
                'field_notes0001' => "Línea 1\nLínea 2",
                'field_date0001' => '28/02/2026',
            ],
            'people' => [['full_name' => 'Persona de prueba']],
        ])->assertCreated();

        $this->assertDatabaseCount('access_requests', 1);
    }

    public function test_client_admin_validates_default_values_by_field_type_and_date_format(): void
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

        $this->get(route('client.request-form.edit'))
            ->assertOk()
            ->assertSee('Agregar campo')
            ->assertSee('request-form-builder-config');

        $fields = [
            ['label' => 'Código', 'type' => 'text', 'max_length' => '5', 'default' => 'ABC'],
            ['label' => 'Notas', 'type' => 'textarea', 'max_lines' => '2', 'default' => "Una\nDos"],
            ['label' => 'Fecha', 'type' => 'date', 'date_format' => 'd/m/Y', 'default' => '28/02/2026'],
            ['label' => 'Categoría', 'type' => 'select', 'options' => "Visita\nMantenimiento", 'default' => 'Mantenimiento'],
            ['label' => 'Soporte', 'type' => 'file', 'extensions' => ['pdf']],
        ];

        foreach ([
            [0, '123456', 'form_fields.0.default'],
            [1, "Una\nDos\nTres", 'form_fields.1.default'],
            [2, '31/02/2026', 'form_fields.2.default'],
            [3, 'Desconocido', 'form_fields.3.default'],
        ] as [$index, $invalidDefault, $errorKey]) {
            $invalidFields = $fields;
            $invalidFields[$index]['default'] = $invalidDefault;
            $this->patch(route('client.request-form.update'), ['form_fields' => $invalidFields])
                ->assertSessionHasErrors($errorKey);
        }
        $this->assertDatabaseCount('organization_form_versions', 0);

        $invalidExtensions = $fields;
        $invalidExtensions[4]['extensions'] = ['exe'];
        $this->patch(route('client.request-form.update'), ['form_fields' => $invalidExtensions])
            ->assertSessionHasErrors('form_fields.4.extensions.0');
        $this->get(route('client.request-form.edit'))
            ->assertOk()
            ->assertSee('placeholder="DD/MM/AAAA"', false)
            ->assertSee('Formato: DD/MM/AAAA');

        $fields[0]['default'] = 'ABC';
        $fields[1]['default'] = "Una\nDos";
        $fields[2]['default'] = '28/02/2026';
        $fields[3]['default'] = 'Mantenimiento';
        $fields[4]['extensions'] = ['pdf', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'txt', 'csv'];

        $this->patch(route('client.request-form.update'), ['form_fields' => $fields])
            ->assertRedirect(route('client.request-form.edit'));

        $schema = $organization->requestFormVersions()->firstOrFail()->schema;
        self::assertSame(5, $schema['fields'][0]['max_length']);
        self::assertSame('ABC', $schema['fields'][0]['default']);
        self::assertSame(2, $schema['fields'][1]['max_lines']);
        self::assertSame("Una\nDos", $schema['fields'][1]['default']);
        self::assertSame('d/m/Y', $schema['fields'][2]['date_format']);
        self::assertSame('28/02/2026', $schema['fields'][2]['default']);
        self::assertSame('Mantenimiento', $schema['fields'][3]['default']);
        self::assertSame(['pdf', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'txt', 'csv'], $schema['fields'][4]['extensions']);
        self::assertArrayNotHasKey('default', $schema['fields'][4]);
    }

    public function test_request_file_extension_must_be_allowed_by_the_client_form(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $version = $organization->requestFormVersions()->create([
            'version' => 1,
            'schema' => ['fields' => [[
                'key' => 'field_document01',
                'label' => 'Documento',
                'type' => 'file',
                'extensions' => ['pdf'],
                'required' => true,
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

        $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/access-requests', [
            'site_id' => $site->id,
            'organization_form_version_id' => $version->id,
            'purpose' => 'Ingreso de prueba',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'form_data' => ['field_document01' => UploadedFile::fake()->create('foto.jpg', 1, 'image/jpeg')],
            'people' => [['full_name' => 'Persona de prueba']],
        ])->assertUnprocessable()->assertJsonValidationErrors('form_data.field_document01');

        $version = $organization->requestFormVersions()->create([
            'version' => 2,
            'schema' => ['fields' => [[
                'key' => 'field_document01',
                'label' => 'Documento',
                'type' => 'file',
                'extensions' => ['xls', 'xlsx', 'txt', 'csv'],
                'required' => true,
            ]]],
            'published_at' => now(),
        ]);

        foreach ([
            ['hoja.xls', 'application/vnd.ms-excel'],
            ['hoja.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['nota.txt', 'text/plain'],
            ['datos.csv', 'text/csv'],
        ] as [$filename, $mimeType]) {
            $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/access-requests', [
                'site_id' => $site->id,
                'organization_form_version_id' => $version->id,
                'purpose' => 'Ingreso de prueba',
                'requested_from' => now()->addDay()->toIso8601String(),
                'requested_until' => now()->addDays(2)->toIso8601String(),
                'form_data' => ['field_document01' => UploadedFile::fake()->create($filename, 1, $mimeType)],
                'people' => [['full_name' => 'Persona de prueba']],
            ])->assertCreated();
        }

        $this->assertDatabaseCount('access_requests', 4);
    }
}
