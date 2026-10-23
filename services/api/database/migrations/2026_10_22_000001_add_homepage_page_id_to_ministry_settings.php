<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->uuid('homepage_page_id')->nullable()->after('timezone');
            $table->foreign('homepage_page_id')->references('id')->on('pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->dropForeign(['homepage_page_id']);
            $table->dropColumn('homepage_page_id');
        });
    }
};
