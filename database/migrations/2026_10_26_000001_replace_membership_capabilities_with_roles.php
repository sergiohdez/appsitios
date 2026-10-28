<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->enum('role', ['client_admin', 'approver', 'requester'])->default('requester')->after('user_id');
        });

        $adminByOrganization = [];
        $memberships = DB::table('memberships')
            ->orderBy('organization_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($memberships as $membership) {
            $role = 'requester';

            if ($membership->can_manage && ! isset($adminByOrganization[$membership->organization_id])) {
                $role = 'client_admin';
                $adminByOrganization[$membership->organization_id] = $membership->id;
            } elseif ($membership->can_approve) {
                $role = 'approver';
            }

            $updates = ['role' => $role];

            if ($role !== 'client_admin' && ! $membership->can_approve && ! $membership->can_request) {
                $updates['is_active'] = false;
            }

            DB::table('memberships')->where('id', $membership->id)->update($updates);
        }

        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn(['can_manage', 'can_approve', 'can_request']);
        });

        Schema::create('client_admin_accounts', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')->primary()->constrained()->cascadeOnDelete();
            $table->ulid('membership_id')->unique();
            $table->timestamps();
            $table->foreign(['organization_id', 'membership_id'])
                ->references(['organization_id', 'id'])->on('memberships')->cascadeOnDelete();
        });

        foreach ($adminByOrganization as $organizationId => $membershipId) {
            DB::table('client_admin_accounts')->insert([
                'organization_id' => $organizationId,
                'membership_id' => $membershipId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_admin_accounts');

        Schema::table('memberships', function (Blueprint $table): void {
            $table->boolean('can_manage')->default(false)->after('user_id');
            $table->boolean('can_approve')->default(false)->after('can_manage');
            $table->boolean('can_request')->default(false)->after('can_approve');
        });

        foreach (DB::table('memberships')->get() as $membership) {
            DB::table('memberships')->where('id', $membership->id)->update([
                'can_manage' => $membership->role === 'client_admin',
                'can_approve' => $membership->role === 'approver',
                'can_request' => in_array($membership->role, ['approver', 'requester'], true),
            ]);
        }

        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
