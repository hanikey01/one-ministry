<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_occurrence_id')->constrained('service_occurrences')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name', 80);
            $table->string('content', 1000);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignUuid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['service_occurrence_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_chat_messages');
    }
};
