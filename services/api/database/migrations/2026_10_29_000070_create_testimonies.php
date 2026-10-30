<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('testimonies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignUuid('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name',160);
            $table->string('title',180);
            $table->string('slug',200)->unique();
            $table->string('category',60)->default('general');
            $table->text('summary')->nullable();
            $table->text('body');
            $table->string('image_url',2048)->nullable();
            $table->boolean('consent_public')->default(false);
            $table->boolean('show_author_name')->default(true);
            $table->boolean('featured')->default(false);
            $table->string('status',24)->default('submitted');
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status','published_at']);
            $table->index(['person_id','created_at']);
            $table->index(['featured','published_at']);
        });
        DB::table('feature_settings')->updateOrInsert(['key'=>'testimonies'],['state'=>'enabled','created_at'=>now(),'updated_at'=>now()]);
    }
    public function down(): void { Schema::dropIfExists('testimonies'); DB::table('feature_settings')->where('key','testimonies')->delete(); }
};
