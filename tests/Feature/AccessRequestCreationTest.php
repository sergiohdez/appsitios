<?php

namespace Tests\Feature;

use App\Models\AccessPerson;
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
            'can_request' => true,
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
}
