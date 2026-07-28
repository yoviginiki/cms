<?php

namespace App\Domain\Collections\Services;

use App\Models\CollectionCategoryNode;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use App\Support\Slugify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for a collection's category tree: create / rename / move / reorder /
 * delete nodes, edit a node's per-node field schema, and assign records to a
 * node. All tree mutations run through here so depth caps, sibling-slug
 * uniqueness and cycle guards stay enforced in one place.
 */
class CollectionCategoryService
{
    /** Trees deeper than this are refused — arbitrary depth, but not unbounded. */
    public const MAX_DEPTH = 10;

    public function __construct(
        private CollectionSchemaValidator $schemaValidator,
        private CategorySchemaResolver $resolver,
    ) {
    }

    // ── Read ────────────────────────────────────────────────────────────────

    /**
     * The whole tree as nested arrays, each node carrying its direct record
     * count. One query for nodes + one grouped count query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(ContentCollection $collection): array
    {
        $nodes = CollectionCategoryNode::where('collection_id', $collection->id)
            ->orderBy('depth')->orderBy('sort_order')->orderBy('name')
            ->get();

        $counts = Record::where('collection_id', $collection->id)
            ->whereNotNull('category_node_id')
            ->select('category_node_id', DB::raw('count(*) as n'))
            ->groupBy('category_node_id')
            ->pluck('n', 'category_node_id');

        $byParent = [];
        foreach ($nodes as $node) {
            $byParent[$node->parent_id ?? '__root__'][] = $node;
        }

        $build = function (?string $parentId) use (&$build, $byParent, $counts) {
            $out = [];
            foreach ($byParent[$parentId ?? '__root__'] ?? [] as $node) {
                $out[] = $this->serialize($node, (int) ($counts[$node->id] ?? 0)) + [
                    'children' => $build($node->id),
                ];
            }

            return $out;
        };

        return $build(null);
    }

    /**
     * @return array<string, mixed> node payload for the API (no children)
     */
    public function serialize(CollectionCategoryNode $node, ?int $recordCount = null): array
    {
        return [
            'id' => $node->id,
            'collection_id' => $node->collection_id,
            'parent_id' => $node->parent_id,
            'name' => $node->name,
            'name_translations' => $node->name_translations ?: (object) [],
            'slug' => $node->slug,
            'sort_order' => $node->sort_order,
            'depth' => $node->depth,
            'schema' => $node->schema ?: ['fields' => []],
            'record_count' => $recordCount,
        ];
    }

    // ── Write ───────────────────────────────────────────────────────────────

    public function create(ContentCollection $collection, Site $site, array $input): CollectionCategoryNode
    {
        $name = $this->cleanName($input['name'] ?? null);
        $parent = $this->resolveParent($collection, $input['parent_id'] ?? null);
        $depth = $parent ? $parent->depth + 1 : 0;
        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'Category trees are limited to ' . (self::MAX_DEPTH + 1) . ' levels.']);
        }

        $schema = array_key_exists('schema', $input)
            ? $this->schemaValidator->validateNodeFields($input['schema'], $site, $collection)
            : ['fields' => []];

        return CollectionCategoryNode::create([
            'collection_id' => $collection->id,
            'site_id' => $site->id,
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($collection, $input['slug'] ?? '', $name, $parent?->id, null),
            'sort_order' => $this->nextSortOrder($collection, $parent?->id, $input['sort_order'] ?? null),
            'depth' => $depth,
            'schema' => $schema,
        ]);
    }

    /** Rename and/or edit the node's own field schema (structure via move()). */
    public function update(CollectionCategoryNode $node, Site $site, array $input): CollectionCategoryNode
    {
        $attrs = [];

        if (array_key_exists('name', $input)) {
            $attrs['name'] = $this->cleanName($input['name']);
        }
        if (array_key_exists('slug', $input) && is_string($input['slug']) && trim($input['slug']) !== '') {
            $attrs['slug'] = $this->uniqueSlug($node->collection, $input['slug'], $attrs['name'] ?? $node->name, $node->parent_id, $node);
        }
        if (array_key_exists('sort_order', $input) && is_numeric($input['sort_order'])) {
            $attrs['sort_order'] = (int) $input['sort_order'];
        }
        if (array_key_exists('schema', $input)) {
            $attrs['schema'] = $this->schemaValidator->validateNodeFields($input['schema'], $site, $node->collection);
        }
        if (array_key_exists('name_translations', $input) && is_array($input['name_translations'])) {
            // {locale: name} — locale keys sanitized, blank names dropped.
            $clean = [];
            foreach ($input['name_translations'] as $locale => $name) {
                if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', (string) $locale) && is_string($name) && trim($name) !== '') {
                    $clean[strtolower((string) $locale)] = mb_substr(trim($name), 0, 255);
                }
            }
            $attrs['name_translations'] = $clean ?: null;
        }

        if ($attrs !== []) {
            $node->update($attrs);
            $node->refresh();
        }

        return $node;
    }

    /**
     * Reparent and/or reorder a node. Guards against cycles (a node can't move
     * under itself or a descendant) and the depth cap (including the moved
     * subtree). Recomputes descendant depths.
     */
    public function move(CollectionCategoryNode $node, ?string $newParentId, ?int $sortOrder): CollectionCategoryNode
    {
        $collection = $node->collection;
        $newParent = $this->resolveParent($collection, $newParentId);

        if ($newParent && $newParent->id === $node->id) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }
        if ($newParent && $this->isDescendant($collection, $newParent, $node)) {
            throw ValidationException::withMessages(['parent_id' => 'Cannot move a category under one of its own descendants.']);
        }

        $newDepth = $newParent ? $newParent->depth + 1 : 0;
        $subtreeExtra = $this->subtreeHeight($collection, $node); // extra levels below the node
        if ($newDepth + $subtreeExtra > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'That move would exceed the ' . (self::MAX_DEPTH + 1) . '-level limit.']);
        }

        DB::transaction(function () use ($node, $newParent, $sortOrder, $collection, $newDepth) {
            $node->update([
                'parent_id' => $newParent?->id,
                'slug' => $this->uniqueSlug($collection, $node->slug, $node->name, $newParent?->id, $node),
                'sort_order' => $sortOrder ?? $this->nextSortOrder($collection, $newParent?->id, null),
                'depth' => $newDepth,
            ]);
            $this->recomputeDepths($collection, $node->id, $newDepth);
        });
        $this->resolver->flush();

        return $node->refresh();
    }

    /**
     * Delete a node. Modes:
     *  - 'reparent' (default): children move up to this node's parent, records
     *    on this node move to its parent (or become unfiled at a root).
     *  - 'cascade': the whole subtree is deleted; records under it are unfiled
     *    (category_node_id → NULL) but the records themselves are kept.
     *
     * @return array{deleted: int, reassigned: int}
     */
    public function delete(CollectionCategoryNode $node, string $mode = 'reparent'): array
    {
        $collection = $node->collection;
        $parentId = $node->parent_id;

        return DB::transaction(function () use ($node, $collection, $parentId, $mode) {
            if ($mode === 'cascade') {
                $ids = $this->subtreeIds($collection, $node);
                $reassigned = Record::where('collection_id', $collection->id)
                    ->whereIn('category_node_id', $ids)
                    ->update(['category_node_id' => null]);
                CollectionCategoryNode::whereIn('id', $ids)->delete();
                $this->resolver->flush();

                return ['deleted' => count($ids), 'reassigned' => $reassigned];
            }

            // reparent: pull children up, move this node's records to the parent
            $newDepth = $parentId
                ? (CollectionCategoryNode::find($parentId)?->depth ?? 0) + 1
                : 0;
            foreach ($node->children as $child) {
                $child->update([
                    'parent_id' => $parentId,
                    'slug' => $this->uniqueSlug($collection, $child->slug, $child->name, $parentId, $child),
                    'depth' => $newDepth,
                ]);
                $this->recomputeDepths($collection, $child->id, $newDepth);
            }
            $reassigned = Record::where('collection_id', $collection->id)
                ->where('category_node_id', $node->id)
                ->update(['category_node_id' => $parentId]);

            $node->delete();
            $this->resolver->flush();

            return ['deleted' => 1, 'reassigned' => $reassigned];
        });
    }

    /**
     * Assign (or unfile with null) a record to a node. Validates the node
     * belongs to the record's collection.
     */
    public function assignRecord(Record $record, ?string $nodeId): Record
    {
        $node = $this->requireNodeOfCollection($record->collection_id, $nodeId);
        $record->update(['category_node_id' => $node?->id]);

        return $record->refresh();
    }

    /**
     * Validate a node id belongs to the given collection; returns the node or
     * null when the id is null/blank. Throws otherwise.
     */
    public function requireNodeOfCollection(string $collectionId, ?string $nodeId): ?CollectionCategoryNode
    {
        if ($nodeId === null || $nodeId === '') {
            return null;
        }
        $node = CollectionCategoryNode::where('collection_id', $collectionId)->find($nodeId);
        if (!$node) {
            throw ValidationException::withMessages(['category_node_id' => 'That category does not belong to this collection.']);
        }

        return $node;
    }

    /** Node ids in a node's subtree (inclusive) — for descendant filters/delete. */
    public function subtreeIds(ContentCollection $collection, CollectionCategoryNode $node): array
    {
        $byParent = CollectionCategoryNode::where('collection_id', $collection->id)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = [];
        $stack = [$node->id];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (in_array($id, $ids, true)) {
                continue;
            }
            $ids[] = $id;
            foreach ($byParent[$id] ?? [] as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    // ── internals ─────────────────────────────────────────────────────────

    private function cleanName(mixed $name): string
    {
        $name = is_string($name) ? trim($name) : '';
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'A category needs a name (max 160 chars).']);
        }

        return $name;
    }

    private function resolveParent(ContentCollection $collection, mixed $parentId): ?CollectionCategoryNode
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }
        if (!is_string($parentId)) {
            throw ValidationException::withMessages(['parent_id' => 'Invalid parent category.']);
        }
        $parent = CollectionCategoryNode::where('collection_id', $collection->id)->find($parentId);
        if (!$parent) {
            throw ValidationException::withMessages(['parent_id' => 'Parent category not found in this collection.']);
        }

        return $parent;
    }

    private function isDescendant(ContentCollection $collection, CollectionCategoryNode $candidate, CollectionCategoryNode $ancestor): bool
    {
        return in_array($candidate->id, $this->subtreeIds($collection, $ancestor), true);
    }

    /** Extra levels below a node (0 = leaf) — for the depth-cap check on move. */
    private function subtreeHeight(ContentCollection $collection, CollectionCategoryNode $node): int
    {
        $nodes = CollectionCategoryNode::where('collection_id', $collection->id)->get(['id', 'parent_id']);
        $byParent = $nodes->groupBy('parent_id');
        $height = function (string $id, int $d) use (&$height, $byParent): int {
            $max = $d;
            foreach ($byParent[$id] ?? [] as $child) {
                $max = max($max, $height($child->id, $d + 1));
            }

            return $max;
        };

        return $height($node->id, 0);
    }

    /** After a reparent, cascade corrected depth values to the subtree. */
    private function recomputeDepths(ContentCollection $collection, string $rootId, int $rootDepth): void
    {
        $byParent = CollectionCategoryNode::where('collection_id', $collection->id)
            ->get(['id', 'parent_id', 'depth'])
            ->groupBy('parent_id');

        $apply = function (string $id, int $depth) use (&$apply, $byParent) {
            foreach ($byParent[$id] ?? [] as $child) {
                CollectionCategoryNode::whereKey($child->id)->update(['depth' => $depth + 1]);
                $apply($child->id, $depth + 1);
            }
        };
        $apply($rootId, $rootDepth);
    }

    private function nextSortOrder(ContentCollection $collection, ?string $parentId, mixed $requested): int
    {
        if (is_numeric($requested)) {
            return (int) $requested;
        }
        $max = CollectionCategoryNode::where('collection_id', $collection->id)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return $max === null ? 0 : $max + 1;
    }

    private function uniqueSlug(ContentCollection $collection, string $requested, string $name, ?string $parentId, ?CollectionCategoryNode $existing): string
    {
        $base = Slugify::slug($requested !== '' ? $requested : $name);
        if ($base === '') {
            $base = 'category';
        }
        $base = mb_substr($base, 0, 150);

        $slug = $base;
        $n = 2;
        while (CollectionCategoryNode::where('collection_id', $collection->id)
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
