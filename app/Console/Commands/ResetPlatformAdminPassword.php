<?php

namespace App\Console\Commands;

use App\Models\PlatformAdminAccount;
use App\Models\PlatformAdminRecoveryCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ResetPlatformAdminPassword extends Command
{
    protected $signature = 'platform-admin:reset-password {--reset-mfa : Obliga a volver a registrar el autenticador y reemplaza los códigos de recuperación}';

    protected $description = 'Restablece la contraseña del administrador único de plataforma desde la consola del servidor.';

    public function handle(): int
    {
        $account = PlatformAdminAccount::query()->with('user')->first();

        if (! $account?->user) {
            $this->error('No se encontró una cuenta administradora de plataforma.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->ask('Confirma el correo del administrador')));

        if (! hash_equals(strtolower($account->user->email), $email)) {
            $this->error('El correo no coincide. No se modificó la cuenta.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Nueva contraseña (mínimo 16 caracteres)');
        $confirmation = (string) $this->secret('Confirma la nueva contraseña');

        if (mb_strlen($password) < 16 || ! hash_equals($password, $confirmation)) {
            $this->error('La contraseña debe tener al menos 16 caracteres y coincidir con la confirmación.');

            return self::FAILURE;
        }

        $resetMfa = (bool) $this->option('reset-mfa');

        DB::transaction(function () use ($account, $password, $resetMfa): void {
            $account->user->forceFill(['password' => Hash::make($password)])->save();
            $account->user->tokens()->delete();

            if ($resetMfa) {
                $account->forceFill([
                    'mfa_secret' => null,
                    'mfa_enabled_at' => null,
                    'mfa_last_used_step' => null,
                    'mfa_required' => true,
                ])->save();
                PlatformAdminRecoveryCode::query()->delete();
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $account->user_id)->delete();
            }
        });

        $this->info('Contraseña restablecida. Las sesiones y tokens existentes fueron revocados.');

        if ($resetMfa) {
            $this->warn('El siguiente inicio de sesión requerirá registrar un nuevo autenticador.');
        }

        return self::SUCCESS;
    }
}
