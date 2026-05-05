<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->string('location')->default('header'); // header, footer, sidebar
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('menu_id');
            $table->uuid('parent_id')->nullable();
            $table->string('label');
            $table->string('url')->nullable();
            $table->uuid('page_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('target')->default('_self');
            $table->string('css_class')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->nullOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['menu_id', 'sort_order']);
        });

        // Self-referencing FK added separately
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('menu_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
