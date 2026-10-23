<?php

namespace App\Console\Commands;

use App\Models\PlatformAdminAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ResetPlatformAdminPassword extends Command
{
    protected $signature = 'platform-admin:reset-password';

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

        DB::transaction(function () use ($account, $password): void {
            $account->user->forceFill(['password' => Hash::make($password)])->save();
            $account->user->tokens()->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $account->user_id)->delete();
            }
        });

        $this->info('Contraseña restablecida. Las sesiones y tokens existentes fueron revocados.');

        return self::SUCCESS;
    }
}
