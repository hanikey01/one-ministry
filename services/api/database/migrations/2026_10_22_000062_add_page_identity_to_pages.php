<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('page_type', 24)->default('standard')->index();
            $table->string('source_key', 80)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropUnique(['source_key']);
            $table->dropIndex(['page_type']);
            $table->dropColumn(['page_type', 'source_key']);
        });
    }
};
