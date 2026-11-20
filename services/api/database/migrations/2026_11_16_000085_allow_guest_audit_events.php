<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->uuid('actor_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Guest audit rows intentionally have no user actor. Contracting this
        // column would either destroy that history or make rollback unsafe.
    }
};
