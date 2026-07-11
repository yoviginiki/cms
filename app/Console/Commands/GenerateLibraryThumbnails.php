<?php

namespace App\Console\Commands;

use App\Domain\Library\Services\LibraryThumbnailService;
use App\Models\BlockTemplate;
use App\Models\Site;
use App\Support\Seeding\SystemRecordSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates cached preview thumbnails for Library items (Builder P1 Slice E).
 * Renders each item's block tree in a real theme context and screenshots it.
 *
 *   php artisan library:thumbnails                 # system starter sections
 *   php artisan library:thumbnails --site=UUID     # + render context site
 *   php artisan library:thumbnails --owned --site=UUID  # that site's own items
 */
class GenerateLibraryThumbnails extends Command
{
    protected $signature = 'library:thumbnails {--site= : Site id for the render/theme context} {--owned : Target the site\'s own items instead of system items} {--force : Regenerate even if a thumbnail already exists}';
    protected $description = 'Render and cache Library item preview thumbnails';

    public function handle(LibraryThumbnailService $service): int
    {
        if (!$service->available()) {
            $this->error('Screenshotting is not available here (proc_open disabled). Run on a host with Playwright.');
            return self::FAILURE;
        }

        // Resolve a context site (privileged read — the command is a CLI op).
        $site = null;
        SystemRecordSeeder::withRlsDisabled('sites', function () use (&$site) {
            $id = $this->option('site');
            $site = $id ? Site::find($id) : Site::query()->orderBy('created_at')->first();
        });
        if (!$site) {
            $this->error('No site found for the render context. Pass --site=UUID.');
            return self::FAILURE;
        }

        // The render materialises a throwaway page under the site, so the DB
        // session must be scoped to that site's tenant (RLS WITH CHECK).
        DB::statement('SET app.current_tenant_id = ' . $this->quote($site->tenant_id));

        $owned = (bool) $this->option('owned');
        $items = $owned
            ? BlockTemplate::where('site_id', $site->id)->get()
            : BlockTemplate::whereNull('site_id')->where('is_system', true)->get();

        if ($items->isEmpty()) {
            $this->info('No matching Library items.');
            return self::SUCCESS;
        }

        $done = 0;
        $skipped = 0;
        foreach ($items as $item) {
            if ($item->preview_image && !$this->option('force')) { $skipped++; continue; }

            $url = $service->generateFor($item, $site);
            if ($url === null) {
                $this->warn("  ✗ {$item->name}");
                continue;
            }

            // System rows can't be written from the app connection — RLS.
            if ($item->is_system) {
                SystemRecordSeeder::withRlsDisabled('block_templates', fn () =>
                    DB::table('block_templates')->where('id', $item->id)->update(['preview_image' => $url, 'updated_at' => now()])
                );
            } else {
                DB::table('block_templates')->where('id', $item->id)->update(['preview_image' => $url, 'updated_at' => now()]);
            }

            $this->line("  ✓ {$item->name}");
            $done++;
        }

        $this->info("Generated {$done} thumbnail(s)" . ($skipped ? ", skipped {$skipped} (already had one)" : '') . '.');
        return self::SUCCESS;
    }

    private function quote(string $v): string
    {
        return "'" . str_replace("'", "''", $v) . "'";
    }
}
