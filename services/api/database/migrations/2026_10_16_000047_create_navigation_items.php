<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('label', 80);
            $table->string('url', 1200);
            $table->string('icon', 40)->nullable();
            $table->uuid('parent_id')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('visible_desktop')->default(true);
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('open_new_tab')->default(false);
            $table->string('required_feature', 80)->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
