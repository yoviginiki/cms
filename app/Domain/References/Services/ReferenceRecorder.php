<?php

namespace App\Domain\References\Services;

use App\Domain\References\ExtractionContext;
use App\Models\Block;
use App\Models\EntityReference;
use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Computes and persists entity-reference edges for a source entity.
 * Persisting is always delete+insert of the source's full edge set, atomically.
 */
class ReferenceRecorder
{
    public function __construct(private ReferenceExtractorRegistry $extractors)
    {
    }

    /**
     * Pure computation: extract the current edge set for a blockable
     * (page|post|template) from its stored block tree. No writes.
     *
     * @return array<int, array{target_type: string, target_id: ?string, kind: string}>
     */
    public function extractForBlockable(Model $blockable, ?Site $site = null): array
    {
        $site ??= $this->resolveSite($blockable);
        if (!$site) {
            return []; // e.g. system templates without a site — nothing to scope edges to
        }

        $context = new ExtractionContext($site);
        $edges = [];

        $blocks = Block::where('blockable_type', $blockable->getMorphClass())
            ->where('blockable_id', $blockable->getKey())
            ->get(['type', 'data']);

        foreach ($blocks as $block) {
            $extractor = $this->extractors->for($block->type);
            if (!$extractor) {
                continue; // unknown type tolerated at runtime; pinned by ExtractorCoverageTest
            }
            foreach ($extractor->extract($block->data ?? [], $context) as $edge) {
                // dedupe on the unique-index key
                $edges["{$edge['target_type']}|{$edge['target_id']}|{$edge['kind']}"] = $edge;
            }
        }

        return array_values($edges);
    }

    /**
     * Recompute + persist a blockable source's edges. Returns the edge count.
     */
    public function recompute(Model $blockable, ?Site $site = null): int
    {
        $site ??= $this->resolveSite($blockable);
        if (!$site) {
            return 0;
        }

        $edges = $this->extractForBlockable($blockable, $site);

        $this->persistEdges($site->id, $blockable->getMorphClass(), $blockable->getKey(), $edges);

        return count($edges);
    }

    /**
     * Pure computation: site-scope edges — entities that render on EVERY page
     * of the site (active theme, menus assigned to a layout location).
     *
     * @return array<int, array{target_type: string, target_id: ?string, kind: string}>
     */
    public function extractSiteScopeEdges(Site $site): array
    {
        $edges = [];

        if ($site->active_theme_id) {
            $edges[] = ['target_type' => 'theme', 'target_id' => $site->active_theme_id, 'kind' => 'site_scope'];
        }

        foreach ($site->menus()->whereNotNull('location')->where('location', '!=', '')->get() as $menu) {
            $edges[] = ['target_type' => 'menu', 'target_id' => $menu->id, 'kind' => 'site_scope'];
        }

        return $edges;
    }

    /**
     * Recompute + persist the site-scope edges (source_type = 'site').
     */
    public function recomputeSiteScope(Site $site): int
    {
        $edges = $this->extractSiteScopeEdges($site);

        $this->persistEdges($site->id, 'site', $site->id, $edges);

        return count($edges);
    }

    /**
     * Atomic delete+insert of a source's full edge set (precomputed).
     */
    public function persistEdges(string $siteId, string $sourceType, string $sourceId, array $edges): void
    {
        DB::transaction(function () use ($siteId, $sourceType, $sourceId, $edges) {
            EntityReference::forSource($sourceType, $sourceId)->delete();

            if ($edges === []) {
                return;
            }

            $now = now();
            EntityReference::insert(array_map(fn (array $edge) => [
                'id' => (string) Str::uuid(),
                'site_id' => $siteId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'target_type' => $edge['target_type'],
                'target_id' => $edge['target_id'],
                'kind' => $edge['kind'],
                'created_at' => $now,
            ], $edges));
        });
    }

    private function resolveSite(Model $blockable): ?Site
    {
        $siteId = $blockable->site_id ?? null;

        return $siteId ? Site::find($siteId) : null;
    }
}
