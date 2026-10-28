<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('site_approvers', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'site_id']);
        });

        Schema::table('site_approvers', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->restrictOnDelete();
        });

        Schema::table('access_requests', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'site_id']);
        });

        Schema::table('access_requests', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_requests', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'site_id']);
        });

        Schema::table('access_requests', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->cascadeOnDelete();
        });

        Schema::table('site_approvers', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'site_id']);
        });

        Schema::table('site_approvers', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->cascadeOnDelete();
        });

        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
