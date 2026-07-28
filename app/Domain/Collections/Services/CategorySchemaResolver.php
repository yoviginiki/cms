<?php

namespace App\Domain\Collections\Services;

use App\Models\CollectionCategoryNode;
use App\Models\ContentCollection;

/**
 * Resolves the EFFECTIVE field schema for a record given the category node it
 * is filed under.
 *
 * Merge rule (documented, deterministic):
 *   effective = base collection fields
 *             ⊕ each ancestor node's own fields, from ROOT down to the node
 *             ⊕ the node's own fields (deepest / most specific)
 *
 * Fields merge BY KEY: a more-specific definition (deeper in the tree) replaces
 * a shallower one with the same key; new keys are appended in
 * base → root → … → leaf order. The base collection always owns title_field /
 * slug_source, so every record keeps a title regardless of its node.
 *
 * When a record has no node (category_node_id NULL) the effective schema is
 * exactly the base collection fields — this is what makes the whole feature
 * backward-compatible: collections/records that never touch the tree behave
 * identically to before.
 */
class CategorySchemaResolver
{
    /** Cache of node ancestor chains (root→leaf) within one request. */
    private array $chainCache = [];

    /**
     * @return array<int, array<string, mixed>> effective, ordered field defs
     */
    public function effectiveFields(ContentCollection $collection, ?string $nodeId): array
    {
        $base = $collection->fields();
        if ($nodeId === null || $nodeId === '') {
            return $base;
        }

        $merged = [];
        foreach ($base as $field) {
            $merged[$field['key']] = $field;
        }
        foreach ($this->ancestorChain($collection, $nodeId) as $node) {
            foreach ($node->ownFields() as $field) {
                $merged[$field['key']] = $field; // deeper overrides by key
            }
        }

        return array_values($merged);
    }

    /**
     * The node ids that make up a node's chain, root→leaf (useful for the UI
     * breadcrumb / knowing which nodes contributed fields).
     *
     * @return array<int, CollectionCategoryNode>
     */
    public function ancestorChain(ContentCollection $collection, string $nodeId): array
    {
        $cacheKey = $collection->id . ':' . $nodeId;
        if (array_key_exists($cacheKey, $this->chainCache)) {
            return $this->chainCache[$cacheKey];
        }

        // Load the whole tree once (collections rarely have huge node counts)
        // and walk parent pointers — no recursive query, no N+1.
        $byId = CollectionCategoryNode::where('collection_id', $collection->id)
            ->get()
            ->keyBy('id');

        $chain = [];
        $current = $byId->get($nodeId);
        $seen = [];
        while ($current && !isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $chain[] = $current;
            $current = $current->parent_id ? $byId->get($current->parent_id) : null;
        }

        $chain = array_reverse($chain); // root → leaf

        return $this->chainCache[$cacheKey] = $chain;
    }

    /** Forget cached chains (call after tree mutations within a request). */
    public function flush(): void
    {
        $this->chainCache = [];
    }
}
