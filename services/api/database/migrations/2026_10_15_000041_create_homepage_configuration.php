<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('theme_family', 40)->default('sanctuary');
            $table->string('hero_layout', 40)->default('immersive');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        Schema::create('homepage_slides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_published')->default(false);
            $table->string('media_type', 24)->default('image');
            $table->string('media_url', 1200)->nullable();
            $table->string('eyebrow', 80)->nullable();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->string('button_label', 60)->nullable();
            $table->string('button_url', 1200)->nullable();
            $table->string('alt_text', 180)->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique('position');
        });
        Schema::create('homepage_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('section_type', 50);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique('section_type');
            $table->unique('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('homepage_slides');
        Schema::dropIfExists('homepage_settings');
    }
};
