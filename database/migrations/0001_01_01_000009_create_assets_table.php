<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->integer('file_size');
            $table->jsonb('dimensions')->nullable();
            $table->jsonb('variants')->default('{}');
            $table->string('checksum', 64);
            $table->string('alt_text')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'mime_type']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
