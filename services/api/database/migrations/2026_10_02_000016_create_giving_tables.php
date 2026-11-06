<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giving_funds', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 160);
            $t->text('description')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('donations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('fund_id')->constrained('giving_funds');
            $t->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->char('currency', 3)->default('USD');
            $t->string('provider', 40);
            $t->string('provider_reference', 191)->nullable();
            $t->string('status', 24)->default('pending');
            $t->string('receipt_email', 254)->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
            $t->unique(['provider', 'provider_reference']);
        });
        Schema::create('giving_webhooks', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('provider', 40);
            $t->string('event_id', 191);
            $t->string('event_type', 100);
            $t->json('payload');
            $t->string('status', 20)->default('received');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'event_id']);
        });
        Schema::create('donation_receipts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('donation_id')->constrained('donations')->cascadeOnDelete();
            $t->string('receipt_number', 80)->unique();
            $t->string('email', 191);
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_receipts');
        Schema::dropIfExists('giving_webhooks');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('giving_funds');
    }
};
