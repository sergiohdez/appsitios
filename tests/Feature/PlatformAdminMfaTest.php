<?php

namespace Tests\Feature;

use App\Models\PlatformAdminAccount;
use App\Models\PlatformAdminRecoveryCode;
use App\Models\User;
use App\Services\PlatformAdminMfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformAdminMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_enrolls_totp_and_recovery_codes_are_single_use(): void
    {
        $admin = User::factory()->create(['email' => 'platform-admin@example.test']);
        PlatformAdminAccount::query()->create([
            'singleton_id' => 1,
            'user_id' => $admin->id,
            'mfa_required' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.mfa.setup'));

        $this->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.mfa.setup'));

        $setupPage = $this->get(route('platform.mfa.setup'))
            ->assertOk()
            ->assertSee('<img class="mfa-qr-code"', false)
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('<details class="mfa-manual">', false);
        $secret = session('platform_admin.mfa_setup_secret');
        self::assertIsString($secret);

        $enrollmentCode = (new Google2FA())->getCurrentOtp($secret);
        $this->post(route('platform.mfa.setup.store'), ['code' => $enrollmentCode])
            ->assertRedirect(route('platform.mfa.recovery-codes'));

        $account = PlatformAdminAccount::query()->firstOrFail();
        self::assertNotNull($account->mfa_enabled_at);
        self::assertSame($secret, $account->mfa_secret);
        self::assertSame(10, PlatformAdminRecoveryCode::query()->count());
        self::assertFalse(app(PlatformAdminMfaService::class)->verifyAndAdvance($account, $enrollmentCode));

        $codesPage = $this->get(route('platform.mfa.recovery-codes'))->assertOk();
        $codes = $codesPage->viewData('codes');
        self::assertCount(10, $codes);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.mfa.challenge'));

        $this->post(route('platform.mfa.challenge.verify'), ['code' => $codes[0]])
            ->assertRedirect(route('platform.dashboard'));
        $this->get(route('platform.dashboard'))->assertOk();

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('platform.mfa.challenge'));

        $this->post(route('platform.mfa.challenge.verify'), ['code' => $codes[0]])
            ->assertSessionHasErrors('code');
        self::assertNotNull(PlatformAdminRecoveryCode::query()->whereNotNull('used_at')->first());
    }
}
