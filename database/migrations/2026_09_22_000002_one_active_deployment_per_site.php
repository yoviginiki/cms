<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F15 (audit 2026-09-22) — at most ONE active (queued/building/deploying)
 * deployment per site, enforced by the database: two requests that both
 * pass the application check can no longer both insert. Rows that are
 * stuck in an active status for over an hour are marked failed first so the
 * index can be created on existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE deployments SET status = 'failed', completed_at = NOW(),
            error_log = COALESCE(error_log, '') || E'\\nReaped by migration: stuck in an active status.'
            WHERE status IN ('queued', 'building', 'deploying') AND created_at < NOW() - INTERVAL '1 hour'");

        // Duplicates inside the last hour (rare): keep the newest, fail the rest.
        DB::statement("UPDATE deployments d SET status = 'failed', completed_at = NOW(),
            error_log = COALESCE(error_log, '') || E'\\nReaped by migration: superseded by a newer active deployment.'
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY site_id ORDER BY created_at DESC) AS rn
                FROM deployments WHERE status IN ('queued', 'building', 'deploying')
            ) x WHERE x.id = d.id AND x.rn > 1");

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS deployments_one_active_per_site
            ON deployments (site_id) WHERE status IN ('queued', 'building', 'deploying')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS deployments_one_active_per_site');
    }
};
