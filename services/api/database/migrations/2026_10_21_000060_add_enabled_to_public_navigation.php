<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->boolean('is_enabled')->default(true)->after('section');
            $table->index(['is_enabled', 'parent_id', 'position'], 'navigation_items_public_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->dropIndex('navigation_items_public_order_index');
            $table->dropColumn('is_enabled');
        });
    }
};
