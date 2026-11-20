<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_update_settings')) return;

        foreach (['repository', 'access_token_encrypted', 'signing_key_id', 'public_key_pem'] as $column) {
            if (Schema::hasColumn('platform_update_settings', $column)) {
                Schema::table('platform_update_settings', fn ($table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_update_settings')) return;

        Schema::table('platform_update_settings', function ($table): void {
            if (! Schema::hasColumn('platform_update_settings', 'repository')) $table->string('repository', 255)->nullable();
            if (! Schema::hasColumn('platform_update_settings', 'access_token_encrypted')) $table->text('access_token_encrypted')->nullable();
            if (! Schema::hasColumn('platform_update_settings', 'signing_key_id')) $table->string('signing_key_id', 120)->nullable();
            if (! Schema::hasColumn('platform_update_settings', 'public_key_pem')) $table->text('public_key_pem')->nullable();
        });
    }
};
