<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table): void {
            $table->foreignUuid('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable()->after('assigned_to');
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table): void {
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('assigned_at');
        });
    }
};
