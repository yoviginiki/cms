<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id')->nullable();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->jsonb('blocks_data');
            $table->string('preview_image')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_templates');
    }
};
