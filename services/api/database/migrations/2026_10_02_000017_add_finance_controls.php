<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('donation_id')->nullable()->constrained('donations')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('provider_event_id', 255);
            $table->string('event_type', 100);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['donation_id', 'occurred_at']);
        });

        Schema::create('reconciliation_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 120)->unique();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('reconciled_at')->useCurrent();
        });

        DB::table('roles')->insertOrIgnore([
            'key' => 'finance_user', 'name' => 'Finance User', 'is_system' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['finance.view' => 'View restricted giving records', 'finance.reconcile' => 'Confirm and reconcile giving records'] as $key => $description) {
            DB::table('permissions')->insertOrIgnore(['key' => $key, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permissions')->insertOrIgnore(['role_key' => 'finance_user', 'permission_key' => $key]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_key', ['finance.view', 'finance.reconcile'])->delete();
        DB::table('permissions')->whereIn('key', ['finance.view', 'finance.reconcile'])->delete();
        DB::table('roles')->where('key', 'finance_user')->delete();
        Schema::dropIfExists('reconciliation_batches');
        Schema::dropIfExists('payment_events');
    }
};
