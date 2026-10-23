<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['is_active'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son válidas.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget([
            'platform_admin.mfa_verified_user_id',
            'platform_admin.mfa_verified_at',
            'platform_admin.mfa_setup_secret',
            'platform_admin.mfa_setup_expires_at',
        ]);

        $platformAdmin = $request->user()->platformAdminAccount()->first();

        if ($platformAdmin) {
            return redirect()->route($platformAdmin->mfa_enabled_at ? 'platform.mfa.challenge' : 'platform.mfa.setup');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
