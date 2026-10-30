<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            $table->string('description', 500)->nullable();
            $table->string('subject', 255);
            $table->text('body_template');
            $table->json('allowed_variables');
            $table->boolean('enabled')->default(true);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('email_delivery_settings', function (Blueprint $table): void {
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_test_succeeded_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->string('last_test_error', 500)->nullable();
        });

        if (Schema::hasTable('navigation_layouts')) {
            DB::table('navigation_layouts')->where('surface', 'staff')->get()->each(function ($layout): void {
                $items = json_decode($layout->sidebar_items, true) ?: [];
                $hasEmail = collect($items)->contains(fn ($item) => (is_string($item) ? $item : ($item['key'] ?? null)) === 'staff.email');
                if (! $hasEmail) {
                    $items[] = ['key' => 'staff.email', 'icon' => 'email', 'group' => 'Administration'];
                    DB::table('navigation_layouts')->where('id', $layout->id)->update(['sidebar_items' => json_encode($items), 'updated_at' => now()]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('navigation_layouts')) {
            DB::table('navigation_layouts')->where('surface', 'staff')->get()->each(function ($layout): void {
                $items = collect(json_decode($layout->sidebar_items, true) ?: [])->reject(fn ($item) => (is_string($item) ? $item : ($item['key'] ?? null)) === 'staff.email')->values()->all();
                DB::table('navigation_layouts')->where('id', $layout->id)->update(['sidebar_items' => json_encode($items), 'updated_at' => now()]);
            });
        }
        Schema::table('email_delivery_settings', function (Blueprint $table): void {
            $table->dropColumn(['last_tested_at', 'last_test_succeeded_at', 'last_test_status', 'last_test_error']);
        });
        Schema::dropIfExists('email_templates');
    }
};
