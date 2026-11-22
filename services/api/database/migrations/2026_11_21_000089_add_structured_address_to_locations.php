<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->string('address_line_1', 255)->nullable()->after('address');
            $table->string('address_line_2', 255)->nullable()->after('address_line_1');
            $table->string('locality', 120)->nullable()->after('address_line_2');
            $table->string('region_code', 16)->nullable()->after('locality');
            $table->string('region_name', 160)->nullable()->after('region_code');
            $table->string('postal_code', 32)->nullable()->after('region_name');
            $table->char('country_code', 2)->nullable()->after('postal_code');
            $table->string('country_name', 120)->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn([
                'address_line_1', 'address_line_2', 'locality', 'region_code',
                'region_name', 'postal_code', 'country_code', 'country_name',
            ]);
        });
    }
};
