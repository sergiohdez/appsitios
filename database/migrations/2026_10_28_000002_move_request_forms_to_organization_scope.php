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
        Schema::create('organization_form_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->useCurrent();
            $table->unique(['organization_id', 'version']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::table('access_requests', function (Blueprint $table): void {
            $table->ulid('organization_form_version_id')->nullable()->after('form_version_id');
            $table->foreign(['organization_id', 'organization_form_version_id'], 'access_requests_org_form_version_foreign')
                ->references(['organization_id', 'id'])->on('organization_form_versions')->restrictOnDelete();
        });

        $now = now();

        foreach (DB::table('organizations')->select('id')->orderBy('id')->get() as $organization) {
            $fields = [];
            $fieldsByKey = [];
            $sites = DB::table('sites')->where('organization_id', $organization->id)->orderBy('id')->get(['id', 'form_schema']);

            foreach ($sites as $site) {
                $currentVersion = DB::table('site_form_versions')
                    ->where('organization_id', $organization->id)
                    ->where('site_id', $site->id)
                    ->orderByDesc('version')
                    ->first(['schema']);
                $legacy = $currentVersion?->schema ?? $site->form_schema;
                $schema = is_string($legacy) ? json_decode($legacy, true) : null;

                foreach (($schema['fields'] ?? []) as $field) {
                    if (! is_array($field) || ! is_string($field['key'] ?? null)) {
                        continue;
                    }

                    $key = $field['key'];

                    if (isset($fieldsByKey[$key])) {
                        if ($fieldsByKey[$key] === $field) {
                            continue;
                        }

                        $key = 'field_'.Str::lower(Str::random(24));
                        $field['key'] = $key;
                    }

                    $fieldsByKey[$key] = $field;
                    $fields[] = $field;
                }
            }

            DB::table('organization_form_versions')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->id,
                'version' => 1,
                'schema' => json_encode(['fields' => $fields], JSON_THROW_ON_ERROR),
                'published_by' => null,
                'published_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('access_requests', function (Blueprint $table): void {
            $table->dropForeign('access_requests_org_form_version_foreign');
            $table->dropColumn('organization_form_version_id');
        });

        Schema::dropIfExists('organization_form_versions');
    }
};
