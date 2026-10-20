<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('name', 160);
            $table->string('email', 254)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_join_requests');
    }
};
