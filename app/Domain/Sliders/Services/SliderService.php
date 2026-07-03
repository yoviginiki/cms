<?php

namespace App\Domain\Sliders\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\References\Services\ReferenceRecorder;
use App\Domain\References\Services\StalenessResolver;
use App\Models\Block;
use App\Models\Site;
use App\Models\Slider;
use Illuminate\Support\Facades\DB;

/**
 * Slider library entities. The block tree lives in the polymorphic blocks
 * table; syncing goes through the shared BlockService (which recomputes the
 * slider's entity_references edges in the same transaction).
 *
 * Publishing fires the EXISTING staleness engine: dependent pages get
 * needs_republish + reason, the stale view / auto-republish toggle take over.
 * No slider-specific republish logic.
 */
class SliderService
{
    public function __construct(
        private BlockService $blocks,
        private StalenessResolver $staleness,
        private ReferenceRecorder $references,
    ) {
    }

    public function create(Site $site, string $name): Slider
    {
        return DB::transaction(function () use ($site, $name) {
            $slider = Slider::create([
                'site_id' => $site->id,
                'name' => $name,
                'status' => 'draft',
            ]);

            // seed the root block + one empty slide so the editor opens usable
            $this->blocks->syncBlocks($slider, [
                [
                    'type' => 'slider', 'level' => 'section', 'order' => 0,
                    'data' => [
                        'height' => ['desktop' => '70vh', 'tablet' => '60vh', 'mobile' => '80vh'],
                        'swiper' => ['effect' => 'slide', 'speed' => 700, 'loop' => true,
                            'autoplay' => false, 'autoplayDelay' => 6000,
                            'navigation' => true, 'pagination' => true,
                            'keyboard' => true, 'pauseOnHover' => true],
                    ],
                    'children' => [
                        ['type' => 'slide', 'level' => 'row', 'order' => 0,
                            'data' => ['background' => ['type' => 'color', 'color' => '#1A1817']]],
                    ],
                ],
            ]);

            return $this->refreshRootPointer($slider);
        });
    }

    /** Full-tree sync via the shared BlockService (edges recompute inside). */
    public function syncBlocks(Slider $slider, array $tree): array
    {
        $result = $this->blocks->syncBlocks($slider, $tree);
        $this->refreshRootPointer($slider);

        return $result;
    }

    /**
     * Publish: flip status, then flag every dependent page/post through the
     * generic staleness walk (slider → embedding pages; transitive by design).
     * Auto-republish (site toggle) rides the same funnel automatically.
     *
     * @return array{pages: int, posts: int, site_wide: bool}
     */
    public function publish(Slider $slider): array
    {
        $slider->update(['status' => 'published', 'published_at' => now()]);

        return $this->staleness->markStale(
            $slider->site,
            'slider',
            $slider->id,
            "Slider '{$slider->name}' updated",
        );
    }

    public function unpublish(Slider $slider): array
    {
        $slider->update(['status' => 'draft']);

        return $this->staleness->markStale(
            $slider->site,
            'slider',
            $slider->id,
            "Slider '{$slider->name}' unpublished",
        );
    }

    private function refreshRootPointer(Slider $slider): Slider
    {
        $root = Block::where('blockable_type', 'slider')
            ->where('blockable_id', $slider->id)
            ->whereNull('parent_block_id')
            ->where('type', 'slider')
            ->first();
        $slider->update(['root_block_id' => $root?->id]);

        return $slider->fresh();
    }
}
