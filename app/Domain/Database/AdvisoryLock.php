<?php

namespace App\Domain\Database;

use Illuminate\Support\Facades\DB;

class AdvisoryLock
{
    /**
     * Acquire an advisory lock (database-specific).
     */
    public static function acquire(string $key): bool
    {
        $lockId = crc32($key);

        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT pg_advisory_lock({$lockId})");
            return true;
        }

        // MySQL
        $result = DB::selectOne("SELECT GET_LOCK(?, 10) as acquired", ["cms_lock_{$key}"]);
        return (bool) ($result->acquired ?? false);
    }

    /**
     * Release an advisory lock.
     */
    public static function release(string $key): void
    {
        $lockId = crc32($key);

        if (config('database.default') === 'pgsql') {
            DB::statement("SELECT pg_advisory_unlock({$lockId})");
            return;
        }

        // MySQL
        DB::statement("SELECT RELEASE_LOCK(?)", ["cms_lock_{$key}"]);
    }

    /**
     * Run a callback within an advisory lock.
     */
    public static function run(string $key, callable $callback): mixed
    {
        static::acquire($key);
        try {
            return $callback();
        } finally {
            static::release($key);
        }
    }
}
