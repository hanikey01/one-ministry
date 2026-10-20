<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->string('logo_path', 500)->nullable()->after('tagline');
            $table->string('dark_logo_path', 500)->nullable()->after('logo_path');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->text('public_address')->nullable()->after('contact_phone');
            $table->string('website_label', 160)->nullable()->after('public_address');
            $table->json('social_links')->nullable()->after('website_label');
        });
    }

    public function down(): void
    {
        Schema::table('ministry_settings', function (Blueprint $table): void {
            $table->dropColumn(['logo_path', 'dark_logo_path', 'contact_phone', 'public_address', 'website_label', 'social_links']);
        });
    }
};
