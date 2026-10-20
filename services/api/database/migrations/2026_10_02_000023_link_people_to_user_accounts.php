<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->nullable()->unique()->after('id')->constrained('users')->nullOnDelete();
            $table->string('preferred_name', 100)->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('preferred_name');
        });
    }
};
