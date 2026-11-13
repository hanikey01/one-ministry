<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('author', 160)->nullable()->after('speaker');
            $table->string('cover_image_path', 500)->nullable()->after('file_size_bytes');
            $table->string('cover_image_alt', 180)->nullable()->after('cover_image_path');
        });
        Schema::table('sermon_series', function (Blueprint $table): void {
            $table->string('cover_image_path', 500)->nullable()->after('cover_key');
            $table->string('cover_image_alt', 180)->nullable()->after('cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', fn (Blueprint $table) => $table->dropColumn(['author', 'cover_image_path', 'cover_image_alt']));
        Schema::table('sermon_series', fn (Blueprint $table) => $table->dropColumn(['cover_image_path', 'cover_image_alt']));
    }
};
