<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_connections', function (Blueprint $table): void {
            $table->string('provider_key', 32)->primary();
            $table->text('api_key_encrypted')->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('connection_status', 24)->default('unconfigured');
            $table->timestampTz('last_checked_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('ai_model_catalog', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider_key', 32);
            $table->string('model_id', 190);
            $table->string('display_name', 255);
            $table->unsignedBigInteger('context_window')->nullable();
            $table->boolean('supports_structured_output')->default(false);
            $table->json('input_modalities')->nullable();
            $table->json('output_modalities')->nullable();
            $table->json('metadata')->nullable();
            $table->string('availability', 24)->default('available');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider_key', 'model_id'], 'ai_catalog_provider_model_unique');
            $table->index(['provider_key', 'availability', 'supports_structured_output'], 'ai_catalog_eligible_idx');
        });

        Schema::create('ai_task_routes', function (Blueprint $table): void {
            $table->string('task_key', 48)->primary();
            $table->string('primary_provider', 32)->nullable();
            $table->string('primary_model', 190)->nullable();
            $table->json('fallback_models')->nullable();
            $table->string('status', 24)->default('unconfigured');
            $table->timestampTz('last_verified_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('ai_configuration_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 80);
            $table->string('subject_id', 150)->nullable();
            $table->json('changes_json');
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'ai_config_audit_subject_idx');
        });

        DB::table('permissions')->insertOrIgnore([
            'key' => 'ai.manage', 'description' => 'Configure AI providers and page drafting models', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['ministry_administrator', 'super_administrator'] as $role) {
            if (DB::table('roles')->where('key', $role)->exists()) {
                DB::table('role_permissions')->insertOrIgnore(['role_key' => $role, 'permission_key' => 'ai.manage']);
            }
        }
        DB::table('feature_settings')->insertOrIgnore([
            'key' => 'ai_page_editor', 'state' => 'disabled', 'changed_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_key', 'ai.manage')->delete();
        DB::table('permissions')->where('key', 'ai.manage')->delete();
        DB::table('feature_settings')->where('key', 'ai_page_editor')->delete();
        Schema::dropIfExists('ai_configuration_audits');
        Schema::dropIfExists('ai_task_routes');
        Schema::dropIfExists('ai_model_catalog');
        Schema::dropIfExists('ai_provider_connections');
    }
};
