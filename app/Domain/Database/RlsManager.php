<?php

namespace App\Domain\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RlsManager
{
    /**
     * Check if RLS is supported (PostgreSQL only).
     */
    public static function isSupported(): bool
    {
        return config('database.default') === 'pgsql';
    }

    /**
     * Enable RLS policies on all tenant-scoped tables.
     */
    public static function enable(): void
    {
        if (!static::isSupported()) {
            Log::info('RLS not available on MySQL. Using application-level tenant scoping only.');
            return;
        }

        $tables = [
            'users' => 'tenant_id',
            'sites' => 'tenant_id',
            'pages' => 'site_id',
            'posts' => 'site_id',
            'categories' => 'site_id',
            'blocks' => null, // polymorphic, scoped via blockable
            'assets' => 'site_id',
            'page_versions' => null,
            'deployments' => 'site_id',
            'deploy_artifacts' => null,
            'themes' => 'site_id',
        ];

        foreach ($tables as $table => $column) {
            if (!$column) continue;

            try {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

                if ($column === 'tenant_id') {
                    DB::statement("
                        CREATE POLICY tenant_isolation ON {$table}
                        USING ({$column} = current_setting('app.current_tenant_id')::uuid)
                    ");
                } else {
                    DB::statement("
                        CREATE POLICY tenant_isolation ON {$table}
                        USING ({$column} IN (
                            SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid
                        ))
                    ");
                }
            } catch (\Throwable $e) {
                Log::warning("RLS setup failed for {$table}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Disable RLS policies.
     */
    public static function disable(): void
    {
        if (!static::isSupported()) return;

        $tables = ['users', 'sites', 'pages', 'posts', 'categories', 'assets', 'deployments', 'themes'];

        foreach ($tables as $table) {
            try {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            } catch (\Throwable) {
                // Ignore
            }
        }
    }
}
