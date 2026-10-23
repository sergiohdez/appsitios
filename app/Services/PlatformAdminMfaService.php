<?php

namespace App\Services;

use App\Models\PlatformAdminAccount;
use App\Models\PlatformAdminRecoveryCode;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class PlatformAdminMfaService
{
    private Google2FA $totp;

    public function __construct()
    {
        $this->totp = new Google2FA();
        $this->totp->setWindow(1);
    }

    public function generateSecret(): string
    {
        return $this->totp->generateSecretKey(32);
    }

    public function provisioningUri(string $email, string $secret): string
    {
        $issuer = (string) config('app.name');
        $label = rawurlencode($issuer.':'.$email);

        return 'otpauth://totp/'.$label.'?secret='.$secret
            .'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function verifyAndAdvance(PlatformAdminAccount $account, string $code): bool
    {
        if (! $account->mfa_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $matchedStep = $this->totp->verifyKeyNewer(
            $account->mfa_secret,
            $code,
            $account->mfa_last_used_step,
            1
        );

        if ($matchedStep === false) {
            return false;
        }

        $account->forceFill(['mfa_last_used_step' => $matchedStep])->save();

        return true;
    }

    public function verifyNewEnrollment(string $secret, string $code): int|false
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // A null old timestamp only returns boolean true from Google2FA, so seed
        // the replay guard with the earliest step accepted by the verification window.
        $matchedStep = $this->totp->verifyKeyNewer(
            $secret,
            $code,
            max(0, $this->totp->getTimestamp() - 2),
            1
        );

        return $matchedStep === false ? false : (int) $matchedStep;
    }

    /** @return list<string> */
    public function replaceRecoveryCodes(): array
    {
        PlatformAdminRecoveryCode::query()->delete();

        $codes = [];

        for ($index = 0; $index < 10; $index++) {
            $rawCode = strtoupper(bin2hex(random_bytes(16)));
            $displayCode = implode('-', str_split($rawCode, 8));

            PlatformAdminRecoveryCode::query()->create([
                'code_hash' => Hash::make($rawCode),
            ]);

            $codes[] = $displayCode;
        }

        return $codes;
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $rawCode = strtoupper((string) preg_replace('/[-\s]/', '', trim($code)));

        if (! preg_match('/^[A-F0-9]{32}$/', $rawCode)) {
            return false;
        }

        $now = now();
        $unusedCodes = PlatformAdminRecoveryCode::query()
            ->whereNull('used_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->lockForUpdate()
            ->get();

        foreach ($unusedCodes as $recoveryCode) {
            if (Hash::check($rawCode, $recoveryCode->code_hash)) {
                $recoveryCode->forceFill(['used_at' => $now])->save();

                return true;
            }
        }

        return false;
    }
}
