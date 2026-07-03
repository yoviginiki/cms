<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel enum() columns are varchar + CHECK on Postgres — extend the
        // allowed values: 'stale_batch' deployments build only flagged pages
        // and park at 'staged' until a human promotes them.
        DB::statement('ALTER TABLE deployments DROP CONSTRAINT IF EXISTS deployments_type_check');
        DB::statement("ALTER TABLE deployments ADD CONSTRAINT deployments_type_check CHECK (type::text = ANY (ARRAY['full', 'partial', 'rollback', 'stale_batch']::text[]))");

        DB::statement('ALTER TABLE deployments DROP CONSTRAINT IF EXISTS deployments_status_check');
        DB::statement("ALTER TABLE deployments ADD CONSTRAINT deployments_status_check CHECK (status::text = ANY (ARRAY['queued', 'building', 'deploying', 'staged', 'live', 'failed', 'rolled_back']::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE deployments DROP CONSTRAINT IF EXISTS deployments_type_check');
        DB::statement("ALTER TABLE deployments ADD CONSTRAINT deployments_type_check CHECK (type::text = ANY (ARRAY['full', 'partial', 'rollback']::text[]))");

        DB::statement('ALTER TABLE deployments DROP CONSTRAINT IF EXISTS deployments_status_check');
        DB::statement("ALTER TABLE deployments ADD CONSTRAINT deployments_status_check CHECK (status::text = ANY (ARRAY['queued', 'building', 'deploying', 'live', 'failed', 'rolled_back']::text[]))");
    }
};
