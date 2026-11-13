<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('feature_settings')->insertOrIgnore([
            'key' => 'attendance', 'state' => 'disabled', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('feature_settings')->where('key', 'attendance')->delete();
    }
};
