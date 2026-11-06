<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_bulk_imports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entity', 16);
            $table->string('status', 16)->default('pending');
            $table->string('errors_path')->nullable();
            $table->unsignedInteger('total_records');
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['organization_id', 'expires_at']);
        });

        Schema::create('client_bulk_import_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('client_bulk_import_id')->constrained('client_bulk_imports')->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->json('data');
            $table->string('status', 16)->default('pending');
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->unique(['client_bulk_import_id', 'source_row'], 'client_bulk_import_records_unique');
            $table->index(['client_bulk_import_id', 'status', 'source_row'], 'client_bulk_import_records_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_bulk_import_records');
        Schema::dropIfExists('client_bulk_imports');
    }
};
