<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One node in a collection's category tree. Arbitrary depth via `parent_id`
 * (NULL = root). `schema` holds the node's OWN field definitions
 * ({ fields: [...] }) — the per-node schema. A record's effective field set is
 * the collection's base fields merged with this node's ancestor chain
 * (root→leaf), most-specific key wins (see CategorySchemaResolver).
 *
 * MULTI-PARENT EXTENSION POINT (deliberately deferred):
 * `parent_id` is the single canonical/primary parent. To let a node hang under
 * several parents later, add a `collection_category_node_parents` pivot
 * (node_id, parent_id) and read parents through parents() below — today it
 * returns the one primary parent, tomorrow it unions the pivot. No column on
 * this table has to change, and nothing that consumes parents() has to be
 * rewritten. Effective-schema resolution would then pick the primary chain
 * (parent_id) to stay deterministic.
 */
class CollectionCategoryNode extends Model
{
    use HasUuids;

    protected $fillable = [
        'collection_id', 'site_id', 'parent_id', 'name', 'name_translations', 'slug', 'sort_order', 'depth', 'schema',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'name_translations' => 'array',
            'sort_order' => 'integer',
            'depth' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ContentCollection::class, 'collection_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'category_node_id');
    }

    /** @return array<int, array<string, mixed>> the node's own field defs */
    public function ownFields(): array
    {
        return $this->schema['fields'] ?? [];
    }

    /**
     * The node's parent ids. Single-element today (the primary parent);
     * the multi-parent seam described in the class docblock unions a pivot
     * here. Callers must treat the result as a set, never as one id.
     *
     * @return array<int, string>
     */
    public function parentIds(): array
    {
        return $this->parent_id ? [$this->parent_id] : [];
    }
}
