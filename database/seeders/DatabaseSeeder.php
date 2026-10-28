<?php

namespace Database\Seeders;

use App\Enums\MembershipRole;
use App\Models\ClientAdminAccount;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);

        // Demo identity creation is deliberately restricted to local development.
        if (! app()->environment('local')) {
            return;
        }

        $email = env('DEMO_ADMIN_EMAIL');
        $password = env('DEMO_ADMIN_PASSWORD');
        $approverEmail = env('DEMO_APPROVER_EMAIL');
        $approverPassword = env('DEMO_APPROVER_PASSWORD');

        $demoEmails = array_values(array_filter([$email, $approverEmail], fn ($value) => is_string($value) && trim($value) !== ''));
        $normalizedDemoEmails = array_map(fn (string $value) => strtolower(trim($value)), $demoEmails);
        $platformAdminEmail = strtolower((string) config('initial-admin.email'));

        if (count($normalizedDemoEmails) !== count(array_unique($normalizedDemoEmails))
            || in_array($platformAdminEmail, $normalizedDemoEmails, true)) {
            throw new RuntimeException('Las cuentas demo deben usar correos distintos entre sí y del administrador de plataforma.');
        }

        if (! $email || ! $password) {
            $this->command?->warn('Set DEMO_ADMIN_EMAIL and DEMO_ADMIN_PASSWORD in .env to seed the local demo account.');

            return;
        }

        $organization = Organization::firstOrCreate(
            ['name' => 'Cliente de demostración'],
            ['billing_email' => $email, 'contact_email' => $email, 'contact_name' => 'Administrador de demostración']
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Administrador de demostración', 'password' => Hash::make($password), 'is_active' => true]
        );

        Membership::updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => MembershipRole::CLIENT_ADMIN, 'is_active' => true]
        );
        $adminMembership = Membership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->firstOrFail();
        ClientAdminAccount::updateOrCreate(
            ['organization_id' => $organization->id],
            ['membership_id' => $adminMembership->id]
        );

        $site = Site::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'DEMO-01'],
            ['name' => 'Sede de demostración', 'address' => 'Bogotá, Colombia', 'timezone' => 'America/Bogota', 'is_active' => true]
        );

        if ($approverEmail && $approverPassword) {
            $approver = User::updateOrCreate(
                ['email' => $approverEmail],
                ['name' => 'Aprobador de demostración', 'password' => Hash::make($approverPassword), 'is_active' => true]
            );
            $approverMembership = Membership::updateOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $approver->id],
                ['role' => MembershipRole::APPROVER, 'is_active' => true]
            );
            $approverMembership->approvableSites()->sync([$site->id => ['organization_id' => $organization->id]]);
        }

        $this->command?->info("Demo local creado para {$email}. Configura también un aprobador separado si quieres probar el ciclo de aprobación.");
    }
}
