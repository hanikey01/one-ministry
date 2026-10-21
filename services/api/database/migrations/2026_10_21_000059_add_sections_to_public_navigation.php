<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->string('section', 60)->default('Main')->after('icon');
            $table->index(['section', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('navigation_items', function (Blueprint $table): void {
            $table->dropIndex(['section', 'position']);
            $table->dropColumn('section');
        });
    }
};
