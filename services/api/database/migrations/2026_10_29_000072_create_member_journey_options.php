<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_step_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 40)->unique();
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestampsTz();
        });

        foreach ([
            ['membership', 'Membership', 'Learn how to become part of the ministry.'],
            ['baptism', 'Baptism', 'Talk with the ministry team about baptism.'],
            ['new_member_class', 'New member class', 'Discover the ministry and meet the people who serve here.'],
            ['discipleship', 'Discipleship', 'Find support for growing in faith and practice.'],
            ['serve', 'Serve', 'Tell the ministry team you would like to contribute.'],
        ] as $order => [$key, $label, $description]) {
            DB::table('next_step_options')->insert([
                'id' => (string) Str::uuid(), 'key' => $key, 'label' => $label,
                'description' => $description, 'active' => true, 'display_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('next_step_options');
    }
};
