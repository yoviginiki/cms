<?php

namespace App\Console\Commands;

use App\Domain\References\Services\ReferenceRecorder;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\ThemeTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the entity_references graph from existing content.
 *
 * ALWAYS run --dry-run first: it prints edge counts per target_type/kind
 * without writing anything.
 */
class ReferencesBackfillCommand extends Command
{
    protected $signature = 'references:backfill
        {--dry-run : Compute and print edge counts without writing anything}';

    protected $description = 'Rebuild entity_references edges for all sites (pages, posts, templates, site-scope)';

    public function handle(ReferenceRecorder $recorder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? 'DRY RUN — nothing will be written.' : 'LIVE RUN — writing edges.');

        // Iterate tenants first: the tenants table has no RLS, but sites does —
        // querying sites without a tenant context returns 0 rows for the app user.
        $tenants = Tenant::all();

        $counts = [];        // "target_type/kind" => n
        $sources = ['page' => 0, 'post' => 0, 'template' => 0, 'site' => 0];
        $edgeTotal = 0;

        $sites = $tenants->flatMap(function (Tenant $tenant) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

            return Site::where('tenant_id', $tenant->id)->get();
        });

        foreach ($sites as $site) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $site->tenant_id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

            $blockables = collect()
                ->concat(Page::withoutGlobalScopes()->where('site_id', $site->id)->get())
                ->concat(Post::withoutGlobalScopes()->where('site_id', $site->id)->get())
                ->concat(ThemeTemplate::withoutGlobalScopes()->where('site_id', $site->id)->get());

            foreach ($blockables as $blockable) {
                $edges = $recorder->extractForBlockable($blockable, $site);
                if (!$dryRun) {
                    $recorder->persistEdges($site->id, $blockable->getMorphClass(), $blockable->getKey(), $edges);
                }

                $sources[$blockable->getMorphClass()]++;
                foreach ($edges as $edge) {
                    $counts["{$edge['target_type']}/{$edge['kind']}"] = ($counts["{$edge['target_type']}/{$edge['kind']}"] ?? 0) + 1;
                    $edgeTotal++;
                }
            }

            // Site-scope edges (theme + located menus)
            $siteEdges = $recorder->extractSiteScopeEdges($site);
            if (!$dryRun) {
                $recorder->recomputeSiteScope($site);
            }
            $sources['site']++;
            foreach ($siteEdges as $edge) {
                $counts["{$edge['target_type']}/{$edge['kind']}"] = ($counts["{$edge['target_type']}/{$edge['kind']}"] ?? 0) + 1;
                $edgeTotal++;
            }
        }

        ksort($counts);
        $this->table(
            ['target_type/kind', 'edges'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($counts), $counts),
        );
        $this->info(sprintf(
            'Sources scanned: %d pages, %d posts, %d templates, %d sites. Total edges: %d.%s',
            $sources['page'], $sources['post'], $sources['template'], $sources['site'],
            $edgeTotal,
            $dryRun ? ' (dry run — nothing written)' : '',
        ));

        return self::SUCCESS;
    }
}
