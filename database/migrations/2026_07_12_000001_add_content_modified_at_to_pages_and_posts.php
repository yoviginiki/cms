<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track F4 — accurate dateModified. `updated_at` is polluted by staleness
 * bookkeeping (needs_republish flag/clear writes bump it on every publish
 * run), so sitemap lastmod and Article dateModified reported publish-run
 * timestamps instead of real content edits. `content_modified_at` is touched
 * only by actual content changes (block sync, title/excerpt/raw_html edits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->timestamp('content_modified_at')->nullable();
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('content_modified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('content_modified_at');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('content_modified_at');
        });
    }
};
