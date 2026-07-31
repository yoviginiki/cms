<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page ownership for author-scoped inline editing (B5).
 *
 * Adds a nullable pages.author_id → users. Existing pages stay null (no author);
 * Page::booted() stamps the creating user on new pages once this column exists.
 * PagePolicy::inlineEdit lets an author inline-edit only pages they own. Until
 * this runs, authors are simply locked out of inline edit — nothing breaks.
 *
 * Backfill (optional, per tenant) is intentionally NOT done here — decide per
 * site whether historical pages should be attributed to a user.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pages', 'author_id')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->uuid('author_id')->nullable()->after('site_id');
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('pages', 'author_id')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropIndex(['author_id']);
            $table->dropColumn('author_id');
        });
    }
};
