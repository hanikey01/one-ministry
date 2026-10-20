<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->string('staff_font', 24)->default('modern')->after('accent_color');
            $table->string('staff_sidebar', 24)->default('deep')->after('staff_font');
            $table->string('staff_density', 24)->default('comfortable')->after('staff_sidebar');
        });
    }

    public function down(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->dropColumn(['staff_font', 'staff_sidebar', 'staff_density']);
        });
    }
};
