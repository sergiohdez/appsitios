<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordRecoveryController extends Controller
{
    private const GENERIC_STATUS = 'Si existe una cuenta asociada a ese correo, recibirás un enlace para restablecer la contraseña.';

    public function create(): ViewContract
    {
        return view('auth.password-request');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Escribe el correo electrónico asociado a tu cuenta.',
            'email.email' => 'Escribe una dirección de correo válida.',
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        try {
            Password::broker()->sendResetLink($data);
        } catch (Throwable $exception) {
            Log::warning('Password recovery email delivery failed.', [
                'exception' => $exception::class,
            ]);
        }

        return redirect()->route('password.request')->with('status', self::GENERIC_STATUS);
    }
}
