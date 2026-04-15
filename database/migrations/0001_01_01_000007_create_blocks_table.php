<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('blockable_id');
            $table->string('blockable_type');
            $table->uuid('parent_block_id')->nullable();
            $table->string('type');
            $table->jsonb('data')->default('{}');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['blockable_type', 'blockable_id', 'order']);
            $table->index(['parent_block_id', 'order']);
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->foreign('parent_block_id')->references('id')->on('blocks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
