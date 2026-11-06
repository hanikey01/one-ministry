<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ministry_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('tagline', 240)->nullable();
            $table->string('public_domain', 191)->unique();
            $table->string('contact_email', 254)->nullable();
            $table->string('timezone', 64);
            $table->char('primary_color', 7)->default('#17324D');
            $table->char('accent_color', 7)->default('#D9A441');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('timezone', 64);
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('feature_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->string('state', 16)->default('disabled');
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX locations_one_primary ON locations (is_primary) WHERE is_primary = true');
            DB::statement("ALTER TABLE feature_settings ADD CONSTRAINT feature_settings_state_check CHECK (state IN ('disabled', 'staff_preview', 'enabled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_settings');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('ministry_settings');
    }
};
