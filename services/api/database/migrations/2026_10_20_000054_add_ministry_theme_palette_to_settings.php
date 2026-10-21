<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->char('primary_color', 7)->default('#343A40')->change();
            $table->char('accent_color', 7)->default('#697386')->change();
            $table->char('light_canvas_color', 7)->default('#F7F7F8');
            $table->char('light_surface_color', 7)->default('#FFFFFF');
            $table->char('light_sidebar_color', 7)->default('#24262A');
            $table->char('dark_canvas_color', 7)->default('#121315');
            $table->char('dark_surface_color', 7)->default('#1C1D20');
            $table->char('dark_sidebar_color', 7)->default('#0B0C0E');
        });
    }

    public function down(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'light_canvas_color', 'light_surface_color', 'light_sidebar_color',
                'dark_canvas_color', 'dark_surface_color', 'dark_sidebar_color',
            ]);
            $table->char('primary_color', 7)->default('#17324D')->change();
            $table->char('accent_color', 7)->default('#D9A441')->change();
        });
    }
};
