<?php

namespace App\Console\Commands;

use App\Domain\Assets\Services\AssetService;
use App\Models\Asset;
use App\Models\Block;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retrofit: import externally-hotlinked images (Pexels/loremflickr/picsum)
 * referenced by existing content into the media library and rewrite the
 * references to library serve URLs — so old content gets the same WebP /
 * dimensions / hashed-static treatment new content gets. Touched pages and
 * posts are flagged stale for republish.
 */
class ImportExternalImagesCommand extends Command
{
    protected $signature = 'assets:import-external
        {--site= : Only this site ID}
        {--dry-run : Report what would be imported without changing anything}';

    protected $description = 'Import hotlinked external images into the media library and rewrite references';

    /** @var array<string, string> URL → serve URL for this run */
    private array $imported = [];

    private int $failed = 0;

    public function handle(AssetService $assets): int
    {
        $dry = (bool) $this->option('dry-run');

        foreach (DB::select('SELECT id FROM tenants') as $tenant) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

            $sites = Site::query()
                ->when($this->option('site'), fn ($q, $id) => $q->whereKey($id))
                ->get();

            foreach ($sites as $site) {
                $this->imported = [];
                $rewrittenBlocks = 0;
                $rewrittenFeatured = 0;

                // Featured images on posts
                foreach ($site->posts()->cursor() as $post) {
                    $new = $this->resolve($assets, $site, $post->featured_image, $post->title, $dry);
                    if ($new !== null && $new !== $post->featured_image) {
                        if (!$dry) {
                            Post::whereKey($post->id)->toBase()->update([
                                'featured_image' => $new,
                                'needs_republish' => true,
                                'needs_republish_reason' => 'External image imported to library',
                            ]);
                        }
                        $rewrittenFeatured++;
                    }
                }

                // Any string leaf inside block data (prefiltered to blocks that
                // mention an importable host at all)
                $blockQuery = Block::query()
                    ->where(function ($q) use ($site) {
                        $q->where(fn ($qq) => $qq->where('blockable_type', 'page')
                            ->whereIn('blockable_id', \App\Models\Page::where('site_id', $site->id)->select('id')))
                          ->orWhere(fn ($qq) => $qq->where('blockable_type', 'post')
                            ->whereIn('blockable_id', Post::where('site_id', $site->id)->select('id')));
                    })
                    ->where(function ($q) {
                        foreach (AssetService::IMPORT_ALLOWED_HOSTS as $host) {
                            $q->orWhereRaw('data::text LIKE ?', ["%{$host}%"]);
                        }
                    });
                foreach ($blockQuery->cursor() as $block) {
                    $data = $block->data ?? [];
                    $changed = false;
                    array_walk_recursive($data, function (&$value) use ($assets, $site, $dry, &$changed) {
                        if (!is_string($value)) {
                            return;
                        }
                        $new = $this->resolve($assets, $site, $value, null, $dry);
                        if ($new !== null && $new !== $value) {
                            $value = $new;
                            $changed = true;
                        }
                    });

                    if ($changed && !$dry) {
                        Block::whereKey($block->id)->toBase()->update(['data' => json_encode($data)]);
                        $parent = $block->blockable;
                        if ($parent && in_array($parent->status ?? null, ['published'], true)) {
                            $parent->newQuery()->whereKey($parent->getKey())->toBase()->update([
                                'needs_republish' => true,
                                'needs_republish_reason' => 'External images imported to library',
                            ]);
                        }
                    }
                    if ($changed) {
                        $rewrittenBlocks++;
                    }
                }

                if ($this->imported !== [] || $rewrittenFeatured > 0 || $rewrittenBlocks > 0) {
                    $this->info(($dry ? '[dry-run] ' : '') . "{$site->name}: " . count($this->imported)
                        . " image(s) imported, {$rewrittenFeatured} featured + {$rewrittenBlocks} block(s) rewritten");
                }
            }
        }

        if ($this->failed > 0) {
            $this->warn("{$this->failed} image(s) failed to import (kept their external URL — see log).");
        }

        return self::SUCCESS;
    }

    /** Returns the serve URL when $value is an importable external image, else null. */
    private function resolve(AssetService $assets, Site $site, ?string $value, ?string $alt, bool $dry): ?string
    {
        if (!$value || !str_starts_with($value, 'https://')) {
            return null;
        }
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        if (!in_array($host, AssetService::IMPORT_ALLOWED_HOSTS, true)) {
            return null;
        }

        if (isset($this->imported[$value])) {
            return $this->imported[$value];
        }
        if ($dry) {
            $this->line("  would import: {$value}");

            return $this->imported[$value] = $value;
        }

        $asset = $assets->importFromUrl($site, $value, $alt, 'retrofit-' . substr(md5($value), 0, 10));
        if (!$asset) {
            $this->failed++;

            return null; // keep external URL
        }

        return $this->imported[$value] = "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve";
    }
}
