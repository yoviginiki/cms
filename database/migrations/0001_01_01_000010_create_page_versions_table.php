<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->jsonb('blocks_snapshot');
            $table->jsonb('seo_snapshot');
            $table->uuid('published_by');
            $table->timestamp('published_at');
            $table->integer('version_number');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('page_id')->references('id')->on('pages')->nullOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users');
            $table->index(['page_id', 'version_number']);
            $table->index(['post_id', 'version_number']);
        });

        DB::statement('ALTER TABLE page_versions ADD CONSTRAINT chk_page_or_post CHECK (
            (page_id IS NOT NULL AND post_id IS NULL) OR
            (page_id IS NULL AND post_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('page_versions');
    }
};
