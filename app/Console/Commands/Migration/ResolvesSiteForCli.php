<?php

namespace App\Console\Commands\Migration;

use App\Models\Site;
use App\Support\Seeding\SystemRecordSeeder;
use Illuminate\Support\Facades\DB;

/**
 * CLI commands have no authenticated user, so app.current_tenant_id is unset
 * and RLS hides every row — including the site we were asked to operate on.
 * Look the site up with RLS briefly disabled, then adopt its tenant context
 * so all subsequent queries run normally scoped.
 */
trait ResolvesSiteForCli
{
    protected function resolveSite(string $slugOrId): ?Site
    {
        $found = null;
        SystemRecordSeeder::withRlsDisabled('sites', function () use ($slugOrId, &$found) {
            $found = DB::table('sites')
                ->where(\Illuminate\Support\Str::isUuid($slugOrId) ? 'id' : 'slug', $slugOrId)
                ->whereNull('deleted_at')
                ->first();
        });
        if (!$found) {
            return null;
        }

        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $found->tenant_id);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        return Site::find($found->id);
    }
}
