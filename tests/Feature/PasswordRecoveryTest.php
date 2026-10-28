<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordRecoveryLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_page_is_linked_from_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Restablece tu contraseña')
            ->assertSee(route('password.email'));
    }

    public function test_email_response_is_indistinguishable_for_known_and_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'person@example.test']);
        $genericMessage = 'Si existe una cuenta asociada a ese correo, recibirás un enlace para restablecer la contraseña.';

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', $genericMessage);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', $genericMessage);

        Notification::assertSentTo($user, PasswordRecoveryLink::class, function (PasswordRecoveryLink $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, 'purpose=reset') && ! str_contains($url, 'email=');
        });
    }

    public function test_successful_recovery_changes_password_and_revokes_sessions_and_api_tokens(): void
    {
        $user = User::factory()->create(['remember_token' => 'previous-remember-token']);
        $token = Password::broker()->createToken($user);
        $user->createToken('active-device');
        DB::table(config('session.table', 'sessions'))->insert([
            'id' => 'existing-authenticated-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => 'stored-session-payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->get(route('password.reset', ['token' => $token, 'purpose' => 'reset']))
            ->assertOk()
            ->assertSee('Restablece tu contraseña')
            ->assertSee('Guardar nueva contraseña');

        $newPassword = 'A-new-secure-password-2026';
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');

        self::assertTrue(password_verify($newPassword, $user->fresh()->password));
        self::assertNotSame('previous-remember-token', $user->fresh()->remember_token);
        self::assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing(config('session.table', 'sessions'), ['id' => 'existing-authenticated-session']);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Another-secure-password-2026',
            'password_confirmation' => 'Another-secure-password-2026',
        ])->assertSessionHasErrors([
            'email' => 'El enlace venció o ya fue utilizado. Solicita un nuevo enlace para restablecer la contraseña.',
        ]);
    }
}
