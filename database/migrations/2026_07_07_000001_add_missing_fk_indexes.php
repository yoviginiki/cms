<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit FIX-A3b — index hot FK / scoping columns that Postgres does not
 * auto-index. These are joined / cascaded / RLS-filtered and were table-scanning.
 */
return new class extends Migration
{
    /** [table, column] pairs (only created if both table and column exist). */
    private array $indexes = [
        ['themes', 'site_id'],
        ['deploy_artifacts', 'deployment_id'],
        ['deploy_artifacts', 'page_id'],
        ['deploy_artifacts', 'post_id'],
        ['menu_items', 'page_id'],
        ['menu_items', 'post_id'],
        ['menu_items', 'category_id'],
        ['menu_items', 'parent_id'],
        ['global_blocks', 'site_id'],
        ['global_blocks', 'block_id'],
        ['popups', 'site_id'],
        ['sites', 'active_theme_id'],
        ['grid_assignments', 'grid_id'],
        ['grid_position_blocks', 'block_id'],
        ['posts', 'author_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $col]) {
            if ($this->columnExists($table, $col)) {
                $name = "idx_{$table}_{$col}";
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$col})");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $col]) {
            DB::statement("DROP INDEX IF EXISTS idx_{$table}_{$col}");
        }
    }

    private function columnExists(string $table, string $col): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name=?",
            [$table, $col]
        );
    }
};
