<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\MembershipRole;
use App\Models\AccessRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Notifications\AccessRequestDecision;
use App\Notifications\AccessRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessRequestNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_notifies_active_site_approvers_but_not_the_requester_or_other_tenants(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['name' => 'Cliente Uno']);
        $site = Site::factory()->create(['organization_id' => $organization->id, 'name' => 'Sede Central']);

        $requester = User::factory()->create(['name' => 'Solicitante Uno']);
        $requesterMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $requesterMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        $approver = User::factory()->create();
        $approverMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $approverMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);

        $inactiveApprover = User::factory()->create();
        $inactiveMembership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $inactiveApprover->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $inactiveMembership->approvableSites()->attach($site->id, ['organization_id' => $organization->id]);
        $inactiveApprover->forceFill(['is_active' => false])->save();

        $otherOrganization = Organization::factory()->create();
        $otherSite = Site::factory()->create(['organization_id' => $otherOrganization->id]);
        $foreignApprover = User::factory()->create();
        $foreignMembership = Membership::factory()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $foreignApprover->id,
            'role' => MembershipRole::APPROVER,
        ]);
        $foreignMembership->approvableSites()->attach($otherSite->id, ['organization_id' => $otherOrganization->id]);

        Sanctum::actingAs($requester);
        $response = $this->postJson('/api/v1/access-requests', [
            'site_id' => $site->id,
            'purpose' => 'Mantenimiento programado',
            'requested_from' => now()->addDay()->toIso8601String(),
            'requested_until' => now()->addDays(2)->toIso8601String(),
            'people' => [['full_name' => 'Visitante confidencial', 'document_type' => 'CC']],
        ])->assertCreated();

        Notification::assertSentTo($approver, AccessRequestSubmitted::class, function (AccessRequestSubmitted $notification, array $channels): bool {
            return $channels === ['mail']
                && $notification->organizationName === 'Cliente Uno'
                && $notification->siteName === 'Sede Central'
                && $notification->requesterName === 'Solicitante Uno';
        });
        Notification::assertNotSentTo($requester, AccessRequestSubmitted::class);
        Notification::assertNotSentTo($inactiveApprover, AccessRequestSubmitted::class);
        Notification::assertNotSentTo($foreignApprover, AccessRequestSubmitted::class);

        $notification = Notification::sent($approver, AccessRequestSubmitted::class)->first();
        self::assertNotNull($notification);
        $mail = $notification->toMail($approver);
        self::assertStringNotContainsString('Visitante confidencial', implode("\n", $mail->introLines));
        self::assertSame(AccessRequestStatus::PENDING->value, $response->json('data.status'));
    }

    public function test_approval_and_rejection_notify_the_requester_with_the_saved_decision(): void
    {
        Notification::fake();

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

        $approved = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
        ]);
        $rejected = AccessRequest::factory()->create([
            'organization_id' => $organization->id,
            'site_id' => $site->id,
            'requester_id' => $requester->id,
        ]);

        Sanctum::actingAs($approver);
        $this->postJson("/api/v1/access-requests/{$approved->id}/approve")->assertOk();
        $this->postJson("/api/v1/access-requests/{$rejected->id}/reject", [
            'comment' => 'Falta indicar el número de orden.',
        ])->assertOk();

        Notification::assertSentTo($requester, AccessRequestDecision::class, function (AccessRequestDecision $notification, array $channels): bool {
            return $channels === ['mail']
                && $notification->status === AccessRequestStatus::APPROVED
                && $notification->reviewerName !== '';
        });
        Notification::assertSentTo($requester, AccessRequestDecision::class, function (AccessRequestDecision $notification, array $channels): bool {
            return $channels === ['mail']
                && $notification->status === AccessRequestStatus::REJECTED
                && $notification->comment === 'Falta indicar el número de orden.';
        });
        Notification::assertNotSentTo($approver, AccessRequestDecision::class);
    }
}
