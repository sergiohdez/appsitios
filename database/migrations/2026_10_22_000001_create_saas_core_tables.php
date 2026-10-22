<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 80)->nullable();
            $table->string('billing_email');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('timezone', 80)->default('America/Bogota');
            $table->string('locale', 12)->default('es');
            $table->json('brand_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_manage')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_request')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 80)->nullable();
            $table->string('address')->nullable();
            $table->string('timezone', 80)->default('America/Bogota');
            $table->json('form_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('site_approvers', function (Blueprint $table): void {
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('site_id');
            $table->ulid('membership_id');
            $table->timestamps();
            $table->primary(['site_id', 'membership_id']);
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->cascadeOnDelete();
            $table->foreign(['organization_id', 'membership_id'])
                ->references(['organization_id', 'id'])->on('memberships')->cascadeOnDelete();
        });

        Schema::create('access_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id');
            $table->foreignId('requester_id');
            $table->string('status', 24)->index();
            $table->string('purpose', 1000);
            $table->dateTime('requested_from');
            $table->dateTime('requested_until');
            $table->json('form_data')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_comment', 1000)->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->cascadeOnDelete();
            $table->foreign(['organization_id', 'requester_id'])
                ->references(['organization_id', 'user_id'])->on('memberships')->cascadeOnDelete();
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['organization_id', 'requester_id', 'created_at']);
            $table->index(['organization_id', 'site_id', 'status']);
        });

        Schema::create('access_people', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('access_request_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 180);
            $table->string('document_type', 40)->nullable();
            $table->text('document_number')->nullable(); // Laravel encrypted cast; ciphertext is longer than the original value.
            $table->string('company', 180)->nullable();
            $table->timestamps();
        });

        Schema::create('access_request_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('access_request_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 120);
            $table->string('subject_type', 180);
            $table->string('subject_id', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('billing_periods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('billable_seats');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->char('currency', 3)->default('COP');
            $table->string('status', 24)->default('draft');
            $table->string('external_invoice_reference')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'period_start', 'period_end']);
        });

        Schema::create('platform_admin_accounts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('singleton_id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('mfa_required')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('platform_admin_recovery_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code_hash', 255)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('platform_admin_recovery_codes');
        Schema::dropIfExists('platform_admin_accounts');
        Schema::dropIfExists('billing_periods');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('access_request_attachments');
        Schema::dropIfExists('access_people');
        Schema::dropIfExists('access_requests');
        Schema::dropIfExists('site_approvers');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
    }
};
