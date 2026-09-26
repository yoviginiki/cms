<?php

namespace App\Console\Commands;

use App\Domain\Grid\Services\GridAreaConverter;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Grid areas as blocks — stage 5 (docs/PLAN-GRID-AREAS-AS-BLOCKS.md §8).
 * Convert one existing site's legacy grid areas to block sections, with a
 * before/after parity report. Always run --dry-run first.
 */
class GridAreasToBlocksCommand extends Command
{
    protected $signature = 'grid:areas-to-blocks
        {site : Site slug}
        {--dry-run : Simulate and report only — nothing is saved}
        {--rollback : Undo a previous conversion}
        {--pages=5 : Pages compared in the parity report}
        {--areas= : Only these areas, comma-separated (e.g. footer or nav,footer)}
        {--details : Print the text that differs per page}';

    protected $description = 'Convert a site\'s legacy grid areas (menus, widgets) into editable block sections';

    public function handle(GridAreaConverter $converter): int
    {
        $site = $this->findSite((string) $this->argument('site'));
        if (!$site) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        if ($this->option('rollback')) {
            $r = $converter->rollback($site);
            $this->info("Restored {$r['restored']} area(s), deleted {$r['sections_deleted']} section(s). Publish the site to apply.");

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $this->line(($dry ? '<comment>DRY RUN</comment> — ' : '') . "Converting grid areas of {$site->name} ({$site->slug})");

        $areas = $this->option('areas') ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('areas'))))) : null;
        $result = $converter->convert($site, $dry, (int) $this->option('pages'), $areas);

        $this->table(['Grid', 'Area', 'Now', 'Action', 'Note'], array_map(fn ($r) => [
            $r['grid'], $r['area'], $r['from'],
            $r['action'] === 'convert' ? '→ section “' . ($r['section'] ?? '') . '”' : 'keep',
            $r['note'],
        ], array_filter($result['plan'], fn ($r) => !in_array($r['position']->type, ['canvas', 'query'], true) || $r['action'] === 'convert')));

        $this->line('');
        $this->line('<info>Parity (visible text of sample pages, before → after)</info>');
        $this->table(['Page', 'Text identical', 'Similarity %', 'Links'], array_map(fn ($p) => [
            $p['page'], $p['text_same'] ? 'yes' : 'NO', $p['text_similarity'], $p['links'],
        ], $result['parity']));

        if ($this->option('details')) {
            foreach ($result['parity'] as $p) {
                if ($p['text_same']) {
                    continue;
                }
                $this->line("<comment>{$p['page']}</comment>");
                foreach (array_slice($p['only_before'], 0, 15) as $l) {
                    $this->line("  - {$l}");
                }
                foreach (array_slice($p['only_after'], 0, 15) as $l) {
                    $this->line("  + {$l}");
                }
            }
        }

        $converted = count(array_filter($result['plan'], fn ($r) => $r['action'] === 'convert'));
        $this->line('');
        $this->line($dry
            ? "Dry run: {$converted} area(s) would be converted. Nothing was saved."
            : "Converted {$converted} area(s) into " . count($result['sections']) . ' section(s). Check the admin preview, then publish. Undo: --rollback');

        return self::SUCCESS;
    }

    /** Sites are tenant-scoped (RLS): find the slug across tenants and set that tenant's context. */
    private function findSite(string $slug): ?Site
    {
        foreach (Tenant::all() as $tenant) {
            DB::statement("SET app.current_tenant_id = '" . preg_replace('/[^a-f0-9\-]/', '', $tenant->id) . "'");
            if ($site = Site::where('slug', $slug)->first()) {
                return $site;
            }
        }

        return null;
    }
}
