<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('theme_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->string('type', 30); // post, archive, header, footer, 404, search
            $table->uuid('category_id')->nullable(); // applies to specific category
            $table->string('post_format', 20)->nullable(); // applies to specific post format
            $table->integer('priority')->default(0); // higher = more specific
            $table->boolean('is_default')->default(false); // site-wide default for this type
            $table->jsonb('settings')->default('{}'); // template-level settings
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'type', 'is_default']);
            $table->index(['site_id', 'type', 'category_id']);
            $table->index(['site_id', 'type', 'post_format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_templates');
    }
};
