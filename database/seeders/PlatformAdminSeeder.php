<?php

namespace Database\Seeders;

use App\Models\PlatformAdminAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('initial-admin.name'));
        $email = strtolower(trim((string) config('initial-admin.email')));
        $password = (string) config('initial-admin.password');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 16) {
            throw new RuntimeException(
                'Configura INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL y una INITIAL_ADMIN_PASSWORD de al menos 16 caracteres antes de ejecutar los seeders.'
            );
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $accounts = PlatformAdminAccount::query()->with('user')->get();

            if ($accounts->count() > 1) {
                throw new RuntimeException('La base de datos contiene más de una cuenta administradora de plataforma.');
            }

            $existingAccount = $accounts->first();

            if ($existingAccount && ((int) $existingAccount->singleton_id !== 1 || strtolower((string) $existingAccount->user?->email) !== $email)) {
                throw new RuntimeException('Ya existe una cuenta administradora de plataforma distinta a la configurada.');
            }

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($password), 'is_active' => true]
            );

            if (! $user->is_active) {
                throw new RuntimeException('La cuenta configurada como administradora está desactivada.');
            }

            if (! $existingAccount) {
                PlatformAdminAccount::query()->create([
                    'singleton_id' => 1,
                    'user_id' => $user->id,
                    // MFA enrollment is not yet implemented in this MVP.
                    'mfa_required' => false,
                ]);
            }
        });

        $this->command?->info('La cuenta administradora de plataforma está lista.');
    }
}
