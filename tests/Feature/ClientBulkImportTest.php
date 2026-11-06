<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\ClientAdminAccount;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\ClientBulkImportFileParser;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ClientBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_headers_use_positional_order(): void
    {
        Notification::fake();
        [$organization] = $this->signInAsClientAdmin();

        $csv = "Ana Prueba,ana@example.test,Solicitante\n"
            ."Luis Aprueba,luis@example.test,Solicitante\n";

        $response = $this->post(route('client.imports.preview', 'users'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 2]);

        $importId = $response->json('id');
        $this->assertNotEmpty($importId);

        $first = $this->post(route('client.imports.next', $importId))->assertOk();
        $first->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->assertDatabaseHas('users', ['email' => 'ana@example.test']);

        $second = $this->post(route('client.imports.next', $importId))->assertOk();
        $second->assertJson(['processed_records' => 2, 'complete' => true]);
        $this->assertDatabaseHas('users', ['email' => 'luis@example.test']);
        Notification::assertSentTo(User::query()->where('email', 'ana@example.test')->firstOrFail(), SetPasswordInvitation::class);
    }

    public function test_users_with_headers_map_named_columns(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Correo electrónico,Perfil,Nombre\nana@example.test,Solicitante,Ana Prueba\n";

        $response = $this->post(route('client.imports.preview', 'users'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 1]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->assertDatabaseHas('users', ['email' => 'ana@example.test', 'name' => 'Ana Prueba']);
    }

    public function test_sites_with_headers_import_timezone_and_code(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Nombre,Código,Dirección,Zona horaria\n"
            ."Sede Norte,N-01,Calle 10,America/Bogota\n";

        $response = $this->post(route('client.imports.preview', 'sites'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 1]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->assertDatabaseHas('sites', ['code' => 'N-01', 'timezone' => 'America/Bogota']);
    }

    public function test_sites_without_headers_use_positional_order(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Sede Sur,S-01,Calle 20,America/Mexico_City\n";

        $response = $this->post(route('client.imports.preview', 'sites'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 1]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->assertDatabaseHas('sites', ['code' => 'S-01', 'timezone' => 'America/Mexico_City']);
    }

    public function test_sites_with_optional_code_and_address_may_omit_them(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        // Only mandatory columns with headers: Nombre + Zona horaria.
        $csv = "Nombre,Zona horaria\nSede Mínima,America/Bogota\n";

        $response = $this->post(route('client.imports.preview', 'sites'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 1]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->assertDatabaseHas('sites', ['name' => 'Sede Mínima', 'code' => null, 'address' => null]);
    }

    public function test_preview_rejects_file_with_headers_missing_mandatory_column(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        // Users: headers present but Perfil is missing (mandatory).
        $csv = "Nombre,Correo electrónico\nAna Prueba,ana@example.test\n";

        $this->postJson(route('client.imports.preview', 'users'), [
            'file' => $this->csvUpload($csv),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('client_bulk_imports', 0);

        // Sites: headers present but Zona horaria is missing (mandatory).
        $csvSites = "Nombre,Código,Dirección\nSede Norte,N-01,Calle 10\n";
        $this->postJson(route('client.imports.preview', 'sites'), [
            'file' => $this->csvUpload($csvSites),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('client_bulk_imports', 0);
    }

    public function test_duplicate_email_within_file_fails_second_record_and_continues(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Ana Prueba,ana@example.test,Solicitante\n"
            ."Ana Duplicada,ana@example.test,Solicitante\n"
            ."Luis Aprueba,luis@example.test,Solicitante\n";

        $response = $this->post(route('client.imports.preview', 'users'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 3]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 2, 'failed_records' => 1, 'record' => ['imported' => false]]);
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 3, 'successful_records' => 2, 'failed_records' => 1, 'complete' => true]);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.test', 'name' => 'Ana Prueba']);
        $this->assertDatabaseHas('users', ['email' => 'luis@example.test']);
    }

    public function test_duplicate_site_code_within_file_fails_second_record(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Sede Norte,N-01,Calle 10,America/Bogota\n"
            ."Sede Norte 2,N-01,Calle 11,America/Bogota\n";

        $response = $this->post(route('client.imports.preview', 'sites'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 2]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 2, 'failed_records' => 1, 'record' => ['imported' => false]]);
        $this->assertDatabaseCount('sites', 1);
    }

    public function test_errors_csv_downloadable_while_import_is_incomplete(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Ana Prueba,correo-mal,Solicitante\n"
            ."Luis Aprueba,luis@example.test,Solicitante\n";

        $response = $this->post(route('client.imports.preview', 'users'), [
            'file' => $this->csvUpload($csv),
        ])->assertCreated()->assertJson(['total_records' => 2]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'failed_records' => 1, 'complete' => false]);

        $download = $this->get(route('client.imports.errors', $importId))->assertOk();
        $content = $download->streamedContent();
        $this->assertStringContainsString('Fila de origen', $content);
        $this->assertStringContainsString('correo-mal', $content);

        Storage::disk('local')->assertExists($this->clientBulkImportPath($importId));
    }

    public function test_requester_cannot_use_bulk_import(): void
    {
        Notification::fake();
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $this->actingAs($requester);

        $csv = "Ana Prueba,ana@example.test,Solicitante\n";
        $this->post(route('client.imports.preview', 'users'), [
            'file' => $this->csvUpload($csv),
        ])->assertForbidden();

        $this->get(route('client.users.index'))->assertForbidden();
    }

    public function test_xlsx_import_round_trip(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $path = $this->createXlsxFile(['Sede XLSX', 'X-01', 'Calle 30', 'America/Bogota']);
        $file = new UploadedFile($path, 'sites.xlsx', mime_content_type($path), null, true);

        $response = $this->post(route('client.imports.preview', 'sites'), [
            'file' => $file,
        ])->assertCreated()->assertJson(['total_records' => 1]);

        $importId = $response->json('id');
        $this->post(route('client.imports.next', $importId))->assertOk()
            ->assertJson(['processed_records' => 1, 'record' => ['imported' => true]]);
        $this->assertDatabaseHas('sites', ['code' => 'X-01']);
    }

    public function test_parser_throws_when_mandatory_header_missing(): void
    {
        Notification::fake();
        $this->signInAsClientAdmin();

        $csv = "Nombre,Correo electrónico\nAna Prueba,ana@example.test\n";
        $path = tempnam(sys_get_temp_dir(), 'bulk-header-missing-').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'users.csv', 'text/csv', null, true);

        try {
            app(ClientBulkImportFileParser::class)->parse($file, 'users');
            $this->fail('Se esperaba ValidationException por columna obligatoria faltante.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        } finally {
            @unlink($path);
        }
    }

    private function csvUpload(string $csv): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk-import-').'.csv';
        file_put_contents($path, $csv);

        return new UploadedFile($path, 'archivo.csv', 'text/csv', null, true);
    }

    private function createXlsxFile(array $values): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach (array_values($values) as $index => $value) {
            $sheet->setCellValue([$index + 1, 1], $value);
        }
        $path = tempnam(sys_get_temp_dir(), 'bulk-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function clientBulkImportPath(string $importId): string
    {
        $organization = Organization::query()->firstOrFail();

        return "client-bulk-imports/{$organization->id}/{$importId}/registros-fallidos.csv";
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
