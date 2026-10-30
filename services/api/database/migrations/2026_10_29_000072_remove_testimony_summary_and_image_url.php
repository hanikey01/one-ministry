<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('testimonies', function (Blueprint $table): void {
            $table->dropColumn(['summary', 'image_url']);
        });
    }

    public function down(): void
    {
        Schema::table('testimonies', function (Blueprint $table): void {
            $table->text('summary')->nullable();
            $table->string('image_url', 2048)->nullable();
        });
    }
};
