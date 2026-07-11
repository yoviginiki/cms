<?php

namespace App\Support\Seeding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Privileged-write helper for SYSTEM records — rows with `site_id`/`tenant_id`
 * NULL and `is_system = true`, shared read-only across every tenant.
 *
 * The tenant-isolation RLS policies use `WITH CHECK (… owns the site …)`, so a
 * NULL-scope INSERT can never come from the app connection (that is the whole
 * point — `is_system` is unforgeable). Seeders therefore temporarily disable RLS
 * on the target table, do their privileged upserts, and re-enable it.
 *
 * This is the ONE privileged-seed path: the starter-section packs use it now,
 * and the first-party theme / style-preset seeders reuse it (build once, not N
 * copies). The re-enable runs in a `finally` so a throwing callback never leaves
 * a table with RLS switched off — a hardening over the older inline pattern.
 */
class SystemRecordSeeder
{
    /**
     * Run $fn with row-level security disabled on $table, then re-enable it.
     * On non-pgsql drivers (e.g. sqlite in tests) it just runs the callback.
     */
    public static function withRlsDisabled(string $table, callable $fn): void
    {
        // $table is always an internal constant, never user input — but guard
        // anyway since it is interpolated straight into DDL.
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException("Unsafe table name: {$table}");
        }

        $pg = DB::connection()->getDriverName() === 'pgsql';
        if ($pg) {
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }

        try {
            $fn();
        } finally {
            if ($pg) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            }
        }
    }
}
