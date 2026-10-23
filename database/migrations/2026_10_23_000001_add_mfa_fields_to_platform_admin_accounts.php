<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_admin_accounts', function (Blueprint $table): void {
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_enabled_at')->nullable();
            $table->unsignedBigInteger('mfa_last_used_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_admin_accounts', function (Blueprint $table): void {
            $table->dropColumn(['mfa_secret', 'mfa_enabled_at', 'mfa_last_used_step']);
        });
    }
};
