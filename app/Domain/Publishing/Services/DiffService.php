<?php

namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Post;

class DiffService
{
    /**
     * Compare current draft blocks with last published version.
     */
    public function diff(Page|Post $content): array
    {
        $isPost = $content instanceof Post;
        $field = $isPost ? 'post_id' : 'page_id';

        // Get last published version
        $lastVersion = PageVersion::where($field, $content->id)
            ->orderByDesc('version_number')
            ->first();

        if (!$lastVersion) {
            return [
                'is_first_publish' => true,
                'blocks' => [],
                'seo' => [],
                'summary' => ['added' => 0, 'modified' => 0, 'removed' => 0, 'moved' => 0],
            ];
        }

        $oldBlocks = $this->flattenBlocks($lastVersion->blocks_snapshot ?? []);
        $newBlocks = $this->flattenBlocks(
            $content->blocks()->orderBy('order')->get()->toArray()
        );

        $oldById = collect($oldBlocks)->keyBy('id');
        $newById = collect($newBlocks)->keyBy('id');

        $blockDiffs = [];
        $summary = ['added' => 0, 'modified' => 0, 'removed' => 0, 'moved' => 0];

        // Check new blocks
        foreach ($newById as $id => $block) {
            if (!$oldById->has($id)) {
                $blockDiffs[] = [
                    'id' => $id,
                    'type' => $block['type'],
                    'status' => 'added',
                    'data' => $block['data'] ?? [],
                    'changes' => [],
                ];
                $summary['added']++;
            } else {
                $oldBlock = $oldById[$id];
                $changes = $this->compareBlockData($oldBlock['data'] ?? [], $block['data'] ?? []);

                // Check if moved (different order or parent)
                $moved = ($oldBlock['order'] ?? 0) !== ($block['order'] ?? 0)
                    || ($oldBlock['parent_block_id'] ?? null) !== ($block['parent_block_id'] ?? null);

                if (!empty($changes)) {
                    $blockDiffs[] = [
                        'id' => $id,
                        'type' => $block['type'],
                        'status' => 'modified',
                        'data' => $block['data'] ?? [],
                        'old_data' => $oldBlock['data'] ?? [],
                        'changes' => $changes,
                    ];
                    $summary['modified']++;
                } elseif ($moved) {
                    $blockDiffs[] = [
                        'id' => $id,
                        'type' => $block['type'],
                        'status' => 'moved',
                        'data' => $block['data'] ?? [],
                        'changes' => [],
                    ];
                    $summary['moved']++;
                } else {
                    $blockDiffs[] = [
                        'id' => $id,
                        'type' => $block['type'],
                        'status' => 'unchanged',
                        'data' => $block['data'] ?? [],
                        'changes' => [],
                    ];
                }
            }
        }

        // Check removed blocks
        foreach ($oldById as $id => $block) {
            if (!$newById->has($id)) {
                $blockDiffs[] = [
                    'id' => $id,
                    'type' => $block['type'],
                    'status' => 'removed',
                    'data' => $block['data'] ?? [],
                    'changes' => [],
                ];
                $summary['removed']++;
            }
        }

        // SEO diff
        $seoDiff = $this->diffSeo(
            $lastVersion->seo_snapshot ?? [],
            $content->seo_meta ?? []
        );

        return [
            'is_first_publish' => false,
            'blocks' => $blockDiffs,
            'seo' => $seoDiff,
            'summary' => $summary,
        ];
    }

    private function flattenBlocks(array $blocks, ?string $parentId = null): array
    {
        $flat = [];
        foreach ($blocks as $block) {
            $children = $block['children'] ?? [];
            unset($block['children']);
            $block['parent_block_id'] = $parentId;
            $flat[] = $block;
            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenBlocks($children, $block['id'] ?? null));
            }
        }
        return $flat;
    }

    private function compareBlockData(array $old, array $new): array
    {
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            if (str_starts_with($key, '_')) continue; // skip internal fields

            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal !== $newVal) {
                $changes[] = [
                    'field' => $key,
                    'old' => $oldVal,
                    'new' => $newVal,
                ];
            }
        }

        return $changes;
    }

    private function diffSeo(array $old, array $new): array
    {
        $diff = [];
        $fields = ['title', 'description', 'og_image', 'no_index', 'og_title', 'og_description'];

        foreach ($fields as $field) {
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            if ($oldVal !== $newVal) {
                $diff[] = ['field' => $field, 'old' => $oldVal, 'new' => $newVal];
            }
        }

        return $diff;
    }
}
