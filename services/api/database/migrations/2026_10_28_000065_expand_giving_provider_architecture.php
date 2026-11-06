<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('giving_funds', function (Blueprint $t): void {
            $t->string('slug', 180)->nullable()->after('name');
            $t->string('currency', 3)->nullable()->after('description');
            $t->json('suggested_amounts')->nullable()->after('currency');
            $t->unsignedBigInteger('goal_amount_minor')->nullable()->after('suggested_amounts');
            $t->boolean('public_visible')->default(true)->after('active');
            $t->unsignedInteger('display_order')->default(0)->after('public_visible');
            $t->uuid('created_by')->nullable()->after('display_order');
            $t->uuid('updated_by')->nullable()->after('created_by');
            $t->index(['active', 'public_visible', 'display_order']);
        });

        Schema::create('payment_processor_configs', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('provider', 40);
            $t->string('country', 2);
            $t->string('currency', 3);
            $t->string('display_name', 120)->nullable();
            $t->string('mode', 10)->default('test');
            $t->boolean('enabled')->default(false);
            $t->unsignedInteger('priority')->default(0);
            $t->text('credentials_encrypted')->nullable();
            $t->string('connection_status', 24)->default('unconfigured');
            $t->timestamp('last_checked_at')->nullable();
            $t->text('last_error')->nullable();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['provider', 'country', 'currency']);
            $t->index(['enabled', 'country', 'currency', 'priority'], 'pay_proc_cfg_enabled_idx');
        });

        Schema::create('offline_giving_methods', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('label',120);
            $t->string('country',2)->nullable();
            $t->string('currency',3)->nullable();
            $t->string('bank_name',160);
            $t->string('account_name',160);
            $t->string('account_number',100)->nullable();
            $t->string('iban',100)->nullable();
            $t->string('swift_bic',32)->nullable();
            $t->string('routing_code',100)->nullable();
            $t->text('instructions')->nullable();
            $t->boolean('active')->default(false);
            $t->unsignedInteger('display_order')->default(0);
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['active','display_order']);
        });

        Schema::table('donations', function (Blueprint $t): void {
            $t->string('internal_reference', 80)->nullable()->after('id');
            $t->string('anonymous_token', 80)->nullable()->after('receipt_email');
            $t->string('donor_name', 160)->nullable()->after('receipt_email');
            $t->boolean('is_anonymous')->default(false)->after('donor_name');
            $t->string('return_url', 2048)->nullable()->after('anonymous_token');
            $t->json('metadata')->nullable()->after('return_url');
            $t->timestamp('expires_at')->nullable()->after('confirmed_at');
            $t->string('refund_status', 24)->default('none')->after('status');
            $t->string('receipt_status', 24)->default('not_issued')->after('refund_status');
            $t->string('reconciliation_status', 24)->default('unreconciled')->after('receipt_status');
            $t->string('provider_customer_id', 255)->nullable()->after('provider_reference');
            $t->string('payment_intent_id', 255)->nullable()->after('provider_customer_id');
            $t->text('failure_reason')->nullable()->after('refund_status');
            $t->unique('internal_reference');
        });

        Schema::create('payment_attempts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('donation_id')->constrained('donations')->restrictOnDelete();
            $t->string('provider', 40);
            $t->string('provider_reference', 191)->nullable();
            $t->string('status', 24)->default('initialized');
            $t->json('response_metadata')->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['donation_id', 'created_at']);
            $t->unique(['provider', 'provider_reference']);
        });

        Schema::table('payment_events', function (Blueprint $t): void {
            $t->json('payload')->nullable()->after('currency');
            $t->string('processing_result', 40)->nullable()->after('payload');
            $t->timestamp('received_at')->nullable()->after('processing_result');
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', fn (Blueprint $t) => $t->dropColumn(['payload', 'processing_result', 'received_at']));
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('offline_giving_methods');
        Schema::table('donations', function (Blueprint $t): void {
            $t->dropUnique(['internal_reference']);
            $t->dropColumn(['internal_reference', 'anonymous_token', 'donor_name', 'is_anonymous', 'return_url', 'metadata', 'expires_at', 'refund_status', 'receipt_status', 'reconciliation_status', 'provider_customer_id', 'payment_intent_id', 'failure_reason']);
        });
        Schema::dropIfExists('payment_processor_configs');
        Schema::table('giving_funds', fn (Blueprint $t) => $t->dropIndex(['active', 'public_visible', 'display_order'])->dropColumn(['slug', 'currency', 'suggested_amounts', 'goal_amount_minor', 'public_visible', 'display_order', 'created_by', 'updated_by']));
    }
};
