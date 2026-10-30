<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sermon_series', 'starts_on')) {
            Schema::table('sermon_series', function (Blueprint $table): void {
                $table->dropColumn('starts_on');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sermon_series', 'starts_on')) {
            Schema::table('sermon_series', function (Blueprint $table): void {
                $table->date('starts_on')->nullable()->after('description');
            });
        }
    }
};
