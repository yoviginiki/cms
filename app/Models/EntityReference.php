<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One edge in the CMS-wide reference graph: source entity → target entity.
 *
 * source: the entity whose content CONTAINS the reference (page|post|template|site)
 * target: the entity being referenced (asset|menu|page|post|category|theme|...)
 * kind:   embeds | links | uses_asset | site_scope | lists
 *
 * target_id NULL = wildcard/site-scope edge ("lists any post", "uses the site theme").
 * Rows are insert-only; a source's edges are recomputed by delete+insert on save.
 */
class EntityReference extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'source_type', 'source_id',
        'target_type', 'target_id', 'kind', 'created_at',
    ];

    /** Scope: all edges owned by a source entity. */
    public function scopeForSource($query, string $sourceType, string $sourceId)
    {
        return $query->where('source_type', $sourceType)->where('source_id', $sourceId);
    }

    /** Scope: inverse lookup — everything that references a target entity. */
    public function scopeForTarget($query, string $siteId, string $targetType, ?string $targetId)
    {
        $query = $query->where('site_id', $siteId)->where('target_type', $targetType);

        // A change to a concrete target also matches wildcard edges ("lists any post")
        return $targetId === null
            ? $query->whereNull('target_id')
            : $query->where(fn ($q) => $q->where('target_id', $targetId)->orWhereNull('target_id'));
    }
}
