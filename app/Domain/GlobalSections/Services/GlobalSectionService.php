<?php

namespace App\Domain\GlobalSections\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\References\Services\StalenessResolver;
use App\Models\BlockTemplate;
use App\Models\GlobalSection;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Global Sections (Builder Experience P2). The block tree lives in the
 * polymorphic blocks table; syncing goes through the shared BlockService (which
 * recomputes the section's entity_references edges in the same transaction).
 *
 * Publishing fires the EXISTING staleness engine: every embedding page gets
 * needs_republish + reason, and the stale view / auto-republish toggle take
 * over. No Global-Sections-specific republish logic — this mirrors SliderService.
 */
class GlobalSectionService
{
    public function __construct(
        private BlockService $blocks,
        private StalenessResolver $staleness,
    ) {}

    /** A blank global section, seeded with one empty section block. */
    public function create(Site $site, string $name): GlobalSection
    {
        return DB::transaction(function () use ($site, $name) {
            $section = GlobalSection::create([
                'site_id' => $site->id,
                'name' => $name,
                'status' => 'draft',
            ]);

            $this->blocks->syncBlocks($section, [
                ['type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [], 'children' => []],
            ]);

            return $section->fresh();
        });
    }

    /** Promote a Library item into a NEW global section (a detached copy). */
    public function promoteFromLibrary(Site $site, BlockTemplate $item, ?string $name = null): GlobalSection
    {
        return DB::transaction(function () use ($site, $item, $name) {
            $section = GlobalSection::create([
                'site_id' => $site->id,
                'name' => $name ?: $item->name,
                'status' => 'draft',
            ]);

            // Fresh IDs so two globals promoted from one item never collide, and
            // the section owns its own blocks (no link back to the library item).
            $this->blocks->syncBlocks($section, $this->freshenTree($item->blocks_data ?? []));

            return $section->fresh();
        });
    }

    /** Full-tree sync via the shared BlockService (edges recompute inside). */
    public function syncBlocks(GlobalSection $section, array $tree): array
    {
        return $this->blocks->syncBlocks($section, $tree);
    }

    /**
     * Publish: flip status, then flag every embedding page/post through the
     * generic staleness walk (global_section → embedding pages; transitive).
     * Auto-republish (site toggle) rides the same funnel automatically.
     *
     * @return array{pages: int, posts: int, site_wide: bool}
     */
    public function publish(GlobalSection $section): array
    {
        $section->update(['status' => 'published', 'published_at' => now()]);

        return $this->staleness->markStale(
            $section->site,
            'global_section',
            $section->id,
            "Global section '{$section->name}' updated",
        );
    }

    public function unpublish(GlobalSection $section): array
    {
        $section->update(['status' => 'draft']);

        return $this->staleness->markStale(
            $section->site,
            'global_section',
            $section->id,
            "Global section '{$section->name}' unpublished",
        );
    }

    /**
     * Strip block ids (fresh ones minted on insert) and normalize order, so a
     * promoted/copied tree never reuses ids across entities.
     *
     * @param array<int,mixed> $tree
     * @return array<int,array>
     */
    private function freshenTree(array $tree): array
    {
        $out = [];
        $i = 0;
        foreach ($tree as $node) {
            if (!is_array($node)) continue;
            $node['id'] = Str::uuid()->toString();
            $node['order'] = $node['order'] ?? $i;
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->freshenTree($node['children']);
            }
            $out[] = $node;
            $i++;
        }
        return $out;
    }
}
