<?php

namespace App\Console\Commands;

use App\Domain\Assets\Services\AssetService;
use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill WebP/responsive variants for existing image assets. Variant
 * generation was broken (silently) for a period, so uploads from that era
 * have none — published sites shipped full-size originals. Run once after
 * deploying the fixed pipeline; safe to re-run (skips assets that already
 * have variants unless --force).
 */
class GenerateAssetVariantsCommand extends Command
{
    protected $signature = 'assets:generate-variants
        {--site= : Only this site ID}
        {--force : Regenerate even when variants already exist}';

    protected $description = 'Backfill image variants (WebP/responsive) for existing assets';

    public function handle(AssetService $assets): int
    {
        $done = 0;
        $skipped = 0;
        $failed = 0;

        foreach (DB::select('SELECT id, name FROM tenants') as $tenant) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

            $query = Asset::query()
                ->where('mime_type', 'like', 'image/%')
                ->where('mime_type', '!=', 'image/svg+xml');
            if ($site = $this->option('site')) {
                $query->where('site_id', $site);
            }

            foreach ($query->cursor() as $asset) {
                if (!empty($asset->variants) && !$this->option('force')) {
                    $skipped++;
                    continue;
                }

                $variants = $assets->regenerateVariants($asset);
                if ($variants !== []) {
                    $done++;
                    $this->line("  ✓ {$asset->original_name} → " . count($variants) . ' variant(s)');
                } else {
                    $failed++;
                    $this->warn("  ✗ {$asset->original_name} — no variants generated (see log)");
                }
            }
        }

        $this->info("Variants backfill: {$done} generated, {$skipped} skipped (already had), {$failed} failed/empty.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
