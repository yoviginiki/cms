<?php

namespace App\Domain\Tenancy;

use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tenant context for UNAUTHENTICATED public requests (F22, audit 2026-09-22).
 *
 * Several public routes used `SELECT id FROM tenants LIMIT 1` — i.e. they
 * only ever worked for the first tenant — and left whatever tenant they last
 * probed in the connection's GUC. This resolver:
 *  - maps a site id or a public host to its tenant by probing tenants once
 *    (the tenants table has no RLS) and caching the mapping — a site's
 *    tenant never changes; misses are cached briefly so a scan per request
 *    cannot be provoked;
 *  - sets the GUC for the resolved tenant, or CLEARS it on a miss so a
 *    negative lookup never leaves the last probed tenant behind;
 *  - offers withTenant() for scoped work that restores the previous context.
 */
final class PublicTenantResolver
{
    private const HIT_TTL = null;      // forever
    private const MISS_TTL = 60;       // seconds

    /** Resolve + set context for a site id; returns the site or null (GUC cleared). */
    public function siteById(?string $siteId): ?Site
    {
        if (!$siteId || !Str::isUuid($siteId)) {
            self::clear();

            return null;
        }
        $tenantId = $this->tenantForSite($siteId);
        if ($tenantId === null) {
            self::clear();

            return null;
        }
        self::set($tenantId);
        $site = Site::find($siteId);
        if (!$site) {
            self::clear();
        }

        return $site;
    }

    /** Resolve + set context for a public host (custom domain); returns the site or null. */
    public function siteByHost(?string $host): ?Site
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            self::clear();

            return null;
        }
        $siteId = Cache::get("public_host_site:{$host}");
        if ($siteId === null) {
            $siteId = $this->scan(fn () => Site::where('custom_domain', $host)->value('id'));
            $siteId === null
                ? Cache::put("public_host_site:{$host}", false, self::MISS_TTL)
                : Cache::forever("public_host_site:{$host}", $siteId);
        }
        if (!$siteId) {
            self::clear();

            return null;
        }

        return $this->siteById((string) $siteId);
    }

    /** Tenant id owning a site id (cached), or null. */
    public function tenantForSite(string $siteId): ?string
    {
        if (!Str::isUuid($siteId)) {
            return null; // never reaches the database (uuid cast would error)
        }
        $cached = Cache::get("site_tenant:{$siteId}");
        if ($cached !== null) {
            return $cached === false ? null : (string) $cached;
        }
        $found = null;
        $this->scan(function () use ($siteId, &$found) {
            if (Site::where('id', $siteId)->exists()) {
                $found = self::current();

                return true;
            }

            return null;
        });
        $found === null
            ? Cache::put("site_tenant:{$siteId}", false, self::MISS_TTL)
            : Cache::forever("site_tenant:{$siteId}", $found);

        return $found;
    }

    /** Run $fn with the given tenant context, restoring the previous one afterwards. */
    public static function withTenant(string $tenantId, callable $fn): mixed
    {
        $previous = self::current();
        self::set($tenantId);
        try {
            return $fn();
        } finally {
            $previous === '' ? self::clear() : self::set($previous);
        }
    }

    public static function set(string $tenantId): void
    {
        $safe = preg_replace('/[^a-f0-9\-]/', '', $tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$safe}'");
    }

    /** The nil UUID: a valid uuid that matches no tenant (policies cast the GUC to uuid, so '' would error). */
    public const NONE = '00000000-0000-0000-0000-000000000000';

    public static function clear(): void
    {
        DB::unprepared("SET app.current_tenant_id = '" . self::NONE . "'");
    }

    /** Current tenant id, or '' when none is set. */
    public static function current(): string
    {
        $row = DB::selectOne("SELECT current_setting('app.current_tenant_id', true) AS t");
        $t = (string) ($row->t ?? '');

        return $t === self::NONE ? '' : $t;
    }

    /**
     * Probe every tenant with $fn until it returns non-null; the context is
     * left on the winning tenant, or cleared when nothing matched.
     */
    private function scan(callable $fn): mixed
    {
        $previous = self::current();
        foreach (Tenant::pluck('id') as $tenantId) {
            self::set((string) $tenantId);
            $result = $fn();
            if ($result !== null) {
                return $result;
            }
        }
        $previous === '' ? self::clear() : self::set($previous);

        return null;
    }
}
