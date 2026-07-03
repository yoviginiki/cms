<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Posts are independently rendered static outputs (blog/{slug}/index.html),
        // so they carry the staleness flag too — not just pages.
        foreach (['pages', 'posts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('needs_republish')->default(false);
                $blueprint->string('needs_republish_reason')->nullable();
            });
        }

        // Partial indexes: the stale-list query is always "WHERE needs_republish",
        // and stale rows are a small minority — no point indexing the false ones.
        DB::statement('
            CREATE INDEX idx_pages_needs_republish
            ON pages (site_id) WHERE needs_republish
        ');
        DB::statement('
            CREATE INDEX idx_posts_needs_republish
            ON posts (site_id) WHERE needs_republish
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_pages_needs_republish');
        DB::statement('DROP INDEX IF EXISTS idx_posts_needs_republish');

        foreach (['pages', 'posts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['needs_republish', 'needs_republish_reason']);
            });
        }
    }
};
