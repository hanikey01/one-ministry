<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_delivery_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 32)->default('custom_smtp');
            $table->boolean('enabled')->default(false);
            $table->string('from_address', 254)->nullable();
            $table->string('from_name', 160)->nullable();
            $table->string('host', 253)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('encryption', 8)->nullable();
            $table->string('username', 254)->nullable();
            $table->text('password_encrypted')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_settings');
    }
};
