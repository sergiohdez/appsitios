<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlatformAdminMfa
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        $account = $user?->platformAdminAccount()->first();

        if (! $account) {
            return $next($request);
        }

        if (! $account->mfa_enabled_at) {
            return $request->expectsJson()
                ? response()->json(['message' => 'El administrador debe completar la inscripción MFA.'], 403)
                : redirect()->route('platform.mfa.setup');
        }

        $verifiedUserId = (int) $request->session()->get('platform_admin.mfa_verified_user_id', 0);
        $verifiedAt = (int) $request->session()->get('platform_admin.mfa_verified_at', 0);
        $isVerified = $verifiedUserId === $user->id && $verifiedAt >= now()->subHours(12)->timestamp;

        if (! $isVerified) {
            $request->session()->forget(['platform_admin.mfa_verified_user_id', 'platform_admin.mfa_verified_at']);

            return $request->expectsJson()
                ? response()->json(['message' => 'Se requiere una verificación MFA reciente.'], 403)
                : redirect()->route('platform.mfa.challenge');
        }

        return $next($request);
    }
}
