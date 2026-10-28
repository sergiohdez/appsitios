<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_form_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('site_id');
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->useCurrent();
            $table->unique(['organization_id', 'site_id', 'version']);
            $table->unique(['organization_id', 'site_id', 'id']);
            $table->foreign(['organization_id', 'site_id'])
                ->references(['organization_id', 'id'])->on('sites')->cascadeOnDelete();
        });

        Schema::table('access_requests', function (Blueprint $table): void {
            $table->ulid('form_version_id')->nullable()->after('form_data');
            $table->foreign(['organization_id', 'site_id', 'form_version_id'], 'access_requests_form_version_tenant_foreign')
                ->references(['organization_id', 'site_id', 'id'])->on('site_form_versions')->restrictOnDelete();
        });

        Schema::table('access_request_attachments', function (Blueprint $table): void {
            $table->string('form_field_key', 64)->nullable()->after('uploaded_by');
        });

        $now = now();
        foreach (DB::table('sites')->select(['id', 'organization_id', 'form_schema'])->get() as $site) {
            $legacySchema = is_string($site->form_schema) ? json_decode($site->form_schema, true) : null;
            $schema = is_array($legacySchema) && isset($legacySchema['fields']) && is_array($legacySchema['fields'])
                ? ['fields' => array_values($legacySchema['fields'])]
                : ['fields' => []];

            DB::table('site_form_versions')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $site->organization_id,
                'site_id' => $site->id,
                'version' => 1,
                'schema' => json_encode($schema, JSON_THROW_ON_ERROR),
                'published_by' => null,
                'published_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('access_request_attachments', function (Blueprint $table): void {
            $table->dropColumn('form_field_key');
        });

        Schema::table('access_requests', function (Blueprint $table): void {
            $table->dropForeign('access_requests_form_version_tenant_foreign');
            $table->dropColumn('form_version_id');
        });

        Schema::dropIfExists('site_form_versions');
    }
};
