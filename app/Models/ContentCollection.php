<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Collection (Track G): a user-defined structured data type. `schema` holds
 * the typed field definitions ({ fields, title_field, slug_source }) validated
 * by CollectionSchemaValidator. Rows live in `records`.
 *
 * Named ContentCollection to avoid endless collisions with
 * Illuminate\Support\Collection at call sites; the table, routes, morph type
 * and UI all say "collection".
 *
 * `is_system` is deliberately NOT fillable — shared system collections
 * (site_id NULL) are seeded by a privileged path, never forged through the app
 * connection (RLS WITH CHECK also blocks NULL-site writes).
 */
class ContentCollection extends Model
{
    use HasUuids;

    protected $table = 'collections';

    public const TIERS = ['static', 'dynamic'];

    protected $fillable = [
        'site_id', 'name', 'slug', 'icon', 'tier', 'schema', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'settings' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'collection_id');
    }

    /** @return array<int, array<string, mixed>> the validated field definitions */
    public function fields(): array
    {
        return $this->schema['fields'] ?? [];
    }

    public function field(string $key): ?array
    {
        foreach ($this->fields() as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    public function titleField(): ?string
    {
        return $this->schema['title_field'] ?? null;
    }

    public function slugSource(): ?string
    {
        return $this->schema['slug_source'] ?? $this->titleField();
    }

    /**
     * Hierarchy (S3): key of the self-relation mode-one field that acts as
     * the parent pointer, or null when the collection is flat. The setting
     * is validated on save; this re-checks the field still qualifies so a
     * later schema edit can't leave a dangling tree config.
     */
    public function hierarchyField(): ?string
    {
        $key = $this->settings['hierarchy_field'] ?? null;
        if (!is_string($key) || $key === '') {
            return null;
        }
        $field = $this->field($key);
        $ok = $field
            && $field['type'] === 'relation'
            && ($field['relation']['mode'] ?? null) === 'one'
            && ($field['relation']['collection_id'] ?? null) === $this->id;

        return $ok ? $key : null;
    }
}
