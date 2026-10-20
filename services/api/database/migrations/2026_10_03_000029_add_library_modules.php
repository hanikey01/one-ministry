<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sermon_series', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->string('cover_provider')->nullable();
            $t->string('cover_key')->nullable();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamps();
            $t->index('status');
        });
        Schema::create('library_categories', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 100)->unique();
            $t->string('slug', 120)->unique();
            $t->string('library', 30)->nullable();
            $t->timestamps();
        });
        Schema::create('library_tags', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 80)->unique();
            $t->string('slug', 100)->unique();
            $t->timestamps();
        });
        Schema::create('library_asset_tags', function (Blueprint $t): void {
            $t->foreignUuid('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $t->foreignUuid('library_tag_id')->constrained('library_tags')->cascadeOnDelete();
            $t->primary(['media_asset_id', 'library_tag_id']);
        });
        Schema::table('media_assets', function (Blueprint $t): void {
            $t->foreignUuid('sermon_series_id')->nullable()->constrained('sermon_series')->nullOnDelete();
            $t->foreignUuid('library_category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $t->string('library', 30)->default('video_sermons');
            $t->string('speaker', 160)->nullable();
            $t->string('scripture_reference', 255)->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->unsignedInteger('position')->nullable();
            $t->string('file_disk', 40)->nullable();
            $t->string('file_path', 500)->nullable();
            $t->string('file_name', 255)->nullable();
            $t->string('file_mime', 120)->nullable();
            $t->unsignedBigInteger('file_size_bytes')->nullable();
            $t->index(['library', 'status', 'published_at']);
        });
        foreach (['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'] as $key) {
            DB::table('feature_settings')->insertOrIgnore(['key' => $key, 'state' => 'disabled', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $t): void {
            $t->dropIndex(['library', 'status', 'published_at']);
            $t->dropConstrainedForeignId('sermon_series_id');
            $t->dropConstrainedForeignId('library_category_id');
            $t->dropColumn(['library', 'speaker', 'scripture_reference', 'duration_seconds', 'position', 'file_disk', 'file_path', 'file_name', 'file_mime', 'file_size_bytes']);
        });
        Schema::dropIfExists('library_asset_tags');
        Schema::dropIfExists('library_tags');
        Schema::dropIfExists('library_categories');
        Schema::dropIfExists('sermon_series');
        DB::table('feature_settings')->whereIn('key', ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'])->delete();
    }
};
