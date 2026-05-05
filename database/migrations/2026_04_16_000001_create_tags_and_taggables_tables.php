<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->uuid('tag_id');
            $table->uuid('taggable_id');
            $table->string('taggable_type');

            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            $table->index(['taggable_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
