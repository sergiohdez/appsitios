<?php

namespace Tests\Feature;

use App\Models\AccessPerson;
use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessRequestCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_submit_a_request_and_document_number_is_encrypted_at_rest(): void
    {
        $tenant = Organization::factory()->create();
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $tenant->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $site = Site::factory()->create(['organization_id' => $tenant->id]);

        Sanctum::actingAs($requester);

        $response = $this->postJson('/api/v1/access-requests', [
            'site_id' => $site->id,
            'purpose' => 'Mantenimiento preventivo',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'people' => [[
                'full_name' => 'Persona de prueba',
                'document_type' => 'CC',
                'document_number' => '123456789',
                'company' => 'Proveedor de prueba',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.site.id', $site->id);

        $person = AccessPerson::query()->firstOrFail();
        self::assertSame('123456789', $person->document_number);
        self::assertNotSame('123456789', DB::table('access_people')->where('id', $person->id)->value('document_number'));
    }

    public function test_request_form_offers_document_types_and_dates_return_clear_validation_messages(): void
    {
        $tenant = Organization::factory()->create();
        $requester = User::factory()->create();
        Membership::factory()->create([
            'organization_id' => $tenant->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::REQUESTER,
        ]);
        $site = Site::factory()->create(['organization_id' => $tenant->id]);

        $this->actingAs($requester)->get('/')
            ->assertOk()
            ->assertSee('<select name="people[0][document_type]">', false)
            ->assertSee('CC — Cédula de Ciudadanía')
            ->assertSee('CE — Cédula de Extranjería')
            ->assertSee('PP — Pasaporte')
            ->assertSee('NIT — Número de Identificación Tributaria');

        Sanctum::actingAs($requester);
        $now = now();
        $future = $now->copy()->addHour()->toIso8601String();
        $later = $now->copy()->addHours(2)->toIso8601String();
        $base = [
            'site_id' => $site->id,
            'purpose' => 'Visita de prueba',
            'requested_from' => $future,
            'requested_until' => $later,
            'people' => [['full_name' => 'Persona de prueba', 'document_type' => 'CC']],
        ];

        $this->postJson('/api/v1/access-requests', array_merge($base, [
            'requested_from' => $now->copy()->subMinute()->toIso8601String(),
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.requested_from.0', 'La fecha y hora de inicio debe ser posterior al momento actual.');

        $this->postJson('/api/v1/access-requests', array_merge($base, [
            'requested_until' => $now->copy()->addMinutes(30)->toIso8601String(),
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.requested_until.0', 'La fecha y hora de finalización debe ser posterior a la hora de inicio y al momento actual.');

        $this->postJson('/api/v1/access-requests', array_merge($base, [
            'requested_until' => $now->copy()->subMinute()->toIso8601String(),
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.requested_until.0', 'La fecha y hora de finalización debe ser posterior a la hora de inicio y al momento actual.');

        $invalidDocumentType = $this->postJson('/api/v1/access-requests', array_merge($base, [
            'people' => [['full_name' => 'Persona de prueba', 'document_type' => 'DNI']],
        ]));
        $invalidDocumentType->assertUnprocessable()->assertJsonValidationErrors('people.0.document_type');
        self::assertSame(
            'Selecciona un tipo de documento válido: CC, CE, PP o NIT.',
            $invalidDocumentType->json('errors')['people.0.document_type'][0],
        );

        foreach (['CC', 'CE', 'PP', 'NIT'] as $documentType) {
            $this->postJson('/api/v1/access-requests', array_merge($base, [
                'people' => [['full_name' => 'Persona de prueba', 'document_type' => $documentType]],
            ]))->assertCreated()
                ->assertJsonPath('data.people.0.document_type', $documentType);
        }
    }
}
