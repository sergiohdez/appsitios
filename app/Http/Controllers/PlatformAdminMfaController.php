<?php

namespace App\Http\Controllers;

use App\Models\PlatformAdminAccount;
use App\Models\PlatformAdminRecoveryCode;
use App\Services\PlatformAdminMfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminMfaController extends Controller
{
    public function showSetup(Request $request, PlatformAdminMfaService $mfa): Response
    {
        $account = $request->user()->platformAdminAccount()->firstOrFail();

        if ($account->mfa_enabled_at) {
            return redirect()->route($this->isVerified($request) ? 'platform.dashboard' : 'platform.mfa.challenge');
        }

        $secret = $request->session()->get('platform_admin.mfa_setup_secret');
        $expiresAt = (int) $request->session()->get('platform_admin.mfa_setup_expires_at', 0);

        if (! $secret || $expiresAt <= now()->timestamp) {
            $secret = $mfa->generateSecret();
            $request->session()->put([
                'platform_admin.mfa_setup_secret' => $secret,
                'platform_admin.mfa_setup_expires_at' => now()->addMinutes(10)->timestamp,
            ]);
        }

        return response()->view('platform.mfa-setup', [
            'secret' => $secret,
            'provisioningUri' => $mfa->provisioningUri($account->user->email, $secret),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function storeSetup(Request $request, PlatformAdminMfaService $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $secret = $request->session()->get('platform_admin.mfa_setup_secret');
        $expiresAt = (int) $request->session()->get('platform_admin.mfa_setup_expires_at', 0);

        if (! $secret || $expiresAt <= now()->timestamp) {
            $request->session()->forget(['platform_admin.mfa_setup_secret', 'platform_admin.mfa_setup_expires_at']);

            throw ValidationException::withMessages([
                'code' => 'El tiempo de configuración venció. Genera una clave nueva e inténtalo de nuevo.',
            ]);
        }

        $codes = DB::transaction(function () use ($request, $mfa, $secret, $data): array {
            $account = PlatformAdminAccount::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            if ($account->mfa_enabled_at) {
                throw ValidationException::withMessages(['code' => 'La autenticación multifactor ya está configurada.']);
            }

            $matchedStep = $mfa->verifyNewEnrollment($secret, $data['code']);

            if ($matchedStep === false) {
                throw ValidationException::withMessages(['code' => 'El código no es válido. Revisa la hora del dispositivo e inténtalo de nuevo.']);
            }

            $account->forceFill([
                'mfa_secret' => $secret,
                'mfa_enabled_at' => now(),
                'mfa_last_used_step' => $matchedStep,
                'mfa_required' => true,
            ])->save();

            return $mfa->replaceRecoveryCodes();
        });

        $request->session()->forget(['platform_admin.mfa_setup_secret', 'platform_admin.mfa_setup_expires_at']);
        $this->markVerified($request);
        $request->session()->flash('platform_admin.recovery_codes', $codes);

        Log::notice('Platform administrator enabled MFA.', ['user_id' => $request->user()->id]);

        return redirect()->route('platform.mfa.recovery-codes');
    }

    public function showChallenge(Request $request): Response
    {
        $account = $request->user()->platformAdminAccount()->firstOrFail();

        if (! $account->mfa_enabled_at) {
            return redirect()->route('platform.mfa.setup');
        }

        if ($this->isVerified($request)) {
            return redirect()->route('platform.dashboard');
        }

        return response()->view('platform.mfa-challenge')
            ->header('Cache-Control', 'no-store, private');
    }

    public function verifyChallenge(Request $request, PlatformAdminMfaService $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $method = 'totp';

        DB::transaction(function () use ($request, $mfa, $data, &$method): void {
            $account = PlatformAdminAccount::query()->whereKey(1)->lockForUpdate()->firstOrFail();

            if (! $account->mfa_enabled_at) {
                throw ValidationException::withMessages(['code' => 'La autenticación multifactor todavía no está configurada.']);
            }

            if (preg_match('/^\d{6}$/', $data['code'])) {
                $valid = $mfa->verifyAndAdvance($account, $data['code']);
            } else {
                $valid = $mfa->consumeRecoveryCode($data['code']);
                $method = 'recovery-code';
            }

            if (! $valid) {
                throw ValidationException::withMessages(['code' => 'El código no es válido o ya fue utilizado.']);
            }
        });

        $this->markVerified($request);
        Log::notice('Platform administrator completed MFA challenge.', [
            'user_id' => $request->user()->id,
            'method' => $method,
        ]);

        return redirect()->route('platform.dashboard');
    }

    public function showRecoveryCodes(Request $request): Response
    {
        $codes = $request->session()->pull('platform_admin.recovery_codes', []);

        if ($codes === []) {
            return redirect()->route('platform.security');
        }

        return response()->view('platform.mfa-recovery-codes', ['codes' => $codes])
            ->header('Cache-Control', 'no-store, private');
    }

    public function security(Request $request): View
    {
        $account = $request->user()->platformAdminAccount()->firstOrFail();

        return view('platform.security', [
            'account' => $account,
            'unusedRecoveryCodes' => PlatformAdminRecoveryCode::query()
                ->whereNull('used_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
        ]);
    }

    public function rotateRecoveryCodes(Request $request, PlatformAdminMfaService $mfa): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $codes = DB::transaction(function () use ($request, $data, $mfa): array {
            $account = PlatformAdminAccount::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $this->assertCurrentPassword($request, $data['password']);

            if (! $mfa->verifyAndAdvance($account, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'El código no es válido o ya fue utilizado.']);
            }

            return $mfa->replaceRecoveryCodes();
        });

        $request->session()->flash('platform_admin.recovery_codes', $codes);
        Log::notice('Platform administrator regenerated MFA recovery codes.', ['user_id' => $request->user()->id]);

        return redirect()->route('platform.mfa.recovery-codes');
    }

    public function resetMfa(Request $request, PlatformAdminMfaService $mfa): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($request, $data, $mfa, $user): void {
            $account = PlatformAdminAccount::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $this->assertCurrentPassword($request, $data['password']);

            if (! $mfa->verifyAndAdvance($account, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'El código no es válido o ya fue utilizado.']);
            }

            $account->forceFill([
                'mfa_secret' => null,
                'mfa_enabled_at' => null,
                'mfa_last_used_step' => null,
                'mfa_required' => true,
            ])->save();

            PlatformAdminRecoveryCode::query()->delete();
            $user->tokens()->delete();

            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        Log::notice('Platform administrator reset MFA and must enroll a new authenticator.', ['user_id' => $user->id]);
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'El segundo factor se restableció. Inicia sesión para configurarlo de nuevo.');
    }

    private function markVerified(Request $request): void
    {
        $request->session()->put([
            'platform_admin.mfa_verified_user_id' => $request->user()->id,
            'platform_admin.mfa_verified_at' => now()->timestamp,
        ]);
        $request->session()->regenerate();
    }

    private function isVerified(Request $request): bool
    {
        return (int) $request->session()->get('platform_admin.mfa_verified_user_id', 0) === $request->user()->id
            && (int) $request->session()->get('platform_admin.mfa_verified_at', 0) >= now()->subHours(12)->timestamp;
    }

    private function assertCurrentPassword(Request $request, string $password): void
    {
        if (! \Illuminate\Support\Facades\Hash::check($password, $request->user()->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'La contraseña actual no es correcta.']);
        }
    }
}
