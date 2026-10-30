<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sermon_series', function (Blueprint $table): void {
            $table->dropColumn('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('sermon_series', function (Blueprint $table): void {
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }
};
