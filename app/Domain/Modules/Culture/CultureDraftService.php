<?php

namespace App\Domain\Modules\Culture;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Blocks\Services\BlockService;
use App\Domain\Posts\Services\PostService;
use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
use App\Models\Post;
use App\Models\Site;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Turns a Culture Engine bulletin payload into a draft Post, reusing the
 * platform's own block persistence (BlockService) and per-block HTMLPurifier
 * chain (SanitizationService). Draft-only: it never publishes.
 */
class CultureDraftService
{
    public function __construct(
        private BlockRegistry $registry,
        private BlockService $blocks,
        private SanitizationService $sanitizer,
        private PostService $posts,
    ) {
    }

    /**
     * Every block type in the (possibly nested) tree that is not registered.
     * Returned distinct so the 422 lists each offending type once.
     */
    public function unknownBlockTypes(array $blocks): array
    {
        $unknown = [];

        $walk = function (array $nodes) use (&$walk, &$unknown): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $type = $node['type'] ?? null;
                if (!is_string($type) || !$this->registry->has($type)) {
                    $unknown[] = is_string($type) && $type !== '' ? $type : '(missing type)';
                }
                if (!empty($node['children']) && is_array($node['children'])) {
                    $walk($node['children']);
                }
            }
        };

        $walk($blocks);

        return array_values(array_unique($unknown));
    }

    /**
     * Recursively run each block's data through its per-block sanitizer BEFORE
     * persistence. Uses a transient (unsaved) Block so we get the exact same
     * cleaning the publish path applies.
     */
    public function sanitizeTree(array $blocks): array
    {
        return array_map(function ($node) {
            if (!is_array($node)) {
                return $node;
            }

            $transient = new Block([
                'type' => $node['type'] ?? '',
                'data' => $node['data'] ?? [],
            ]);
            $node['data'] = $this->sanitizer->sanitizeBlock($transient);

            if (!empty($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->sanitizeTree($node['children']);
            }

            return $node;
        }, array_values($blocks));
    }

    public function createDraft(Site $site, array $payload): Post
    {
        $post = $this->posts->createPost([
            'title' => $payload['title'] ?? 'Untitled bulletin',
            'slug' => $payload['slug'] ?? null,
            'excerpt' => $payload['excerpt'] ?? null,
            'status' => 'draft', // never published — Prime Directive
            'seo_meta' => ['culture_engine' => $payload['metadata'] ?? []],
        ], $site);

        $tagIds = $this->resolveTags($site, $payload['tags'] ?? []);
        if ($tagIds) {
            $post->tags()->sync($tagIds);
        }

        $sanitized = $this->sanitizeTree($payload['blocks'] ?? []);
        $this->blocks->syncBlocks($post, $sanitized);

        return $post;
    }

    /** Resolve incoming tag names to per-site Tag ids, creating any missing. */
    private function resolveTags(Site $site, array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tag = Tag::firstOrCreate(
                ['site_id' => $site->id, 'slug' => Str::slug($name)],
                ['name' => $name],
            );
            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }
}
