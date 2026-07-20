<?php

namespace App\Support\Blocks;

use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;

/**
 * Type-aware, escape-safe display rendering for record field values — shared
 * by the field-value / record-loop / record-image Blades, the fallback
 * record views and the search-index thumbnails. Every output is HTML-safe:
 * everything is e()'d except rich_text, which was purified at save.
 */
class RecordDisplay
{
    private const CURRENCY_SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'BGN' => 'лв', 'CHF' => 'CHF'];

    public static function assetUrl(Site $site, ?string $assetId): ?string
    {
        if (!$assetId || !preg_match('/^[0-9a-f-]{36}$/i', $assetId)) {
            return null;
        }

        // AssetPublisher::rewriteHtml converts these to hashed static paths at publish.
        return "/api/v1/sites/{$site->id}/assets/{$assetId}/serve";
    }

    /** Public URL path prefix for a collection (settings override, else slug). */
    public static function pathPrefix(ContentCollection $collection): string
    {
        $prefix = $collection->settings['path_prefix'] ?? '';

        return preg_match('/^[a-z0-9-]+$/', (string) $prefix) ? $prefix : $collection->slug;
    }

    public static function recordUrl(ContentCollection $collection, Record $record): string
    {
        $segments = array_map(fn (Record $r) => $r->slug, self::ancestors($collection, $record));
        $segments[] = $record->slug;

        return '/' . self::pathPrefix($collection) . '/' . implode('/', $segments) . '/';
    }

    /**
     * Hierarchy (S3): the published-ancestor chain root→…→parent, empty for
     * flat collections. Walks the hierarchy field's edges upward with cycle
     * and depth protection; unpublished ancestors are skipped from URLs so
     * a draft parent never 404s its children.
     *
     * @return array<int, Record>
     */
    public static function ancestors(ContentCollection $collection, Record $record): array
    {
        $key = $collection->hierarchyField();
        if (!$key) {
            return [];
        }

        $chain = [];
        $seen = [$record->id => true];
        $current = $record;
        for ($depth = 0; $depth < \App\Domain\Collections\Services\RecordService::MAX_TREE_DEPTH; $depth++) {
            $parentId = \App\Models\RecordRelation::where('from_record_id', $current->id)
                ->where('relation_key', $key)
                ->orderBy('position')
                ->value('to_record_id');
            if (!$parentId || isset($seen[$parentId])) {
                break;
            }
            $seen[$parentId] = true;
            $parent = Record::find($parentId);
            if (!$parent) {
                break;
            }
            if ($parent->status === 'published') {
                array_unshift($chain, $parent);
            }
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Published direct children of a record in its hierarchy, ordered by
     * position then title. Empty for flat collections.
     *
     * @return \Illuminate\Support\Collection<int, Record>
     */
    public static function children(ContentCollection $collection, Record $record)
    {
        $key = $collection->hierarchyField();
        if (!$key) {
            return collect();
        }

        $childIds = \App\Models\RecordRelation::where('to_record_id', $record->id)
            ->where('relation_key', $key)
            ->pluck('from_record_id');

        return Record::whereIn('id', $childIds)
            ->where('status', 'published')
            ->orderBy('position')
            ->orderBy('title')
            ->get();
    }

    public static function currencySymbol(ContentCollection $collection, Site $site): string
    {
        $code = $collection->settings['currency'] ?? $site->settings['currency'] ?? 'EUR';

        return self::CURRENCY_SYMBOLS[$code] ?? $code;
    }

    /** First image-type field key in the schema (record-image/thumb default). */
    public static function firstImageField(ContentCollection $collection): ?string
    {
        foreach ($collection->fields() as $field) {
            if ($field['type'] === 'image') {
                return $field['key'];
            }
        }

        return null;
    }

    public static function thumbUrl(Site $site, ContentCollection $collection, Record $record): ?string
    {
        $key = self::firstImageField($collection);
        $value = $key ? ($record->data[$key] ?? null) : null;

        if (!$value) {
            // Fall back to the first gallery image.
            foreach ($collection->fields() as $field) {
                if ($field['type'] === 'gallery') {
                    $ids = $record->data[$field['key']] ?? [];
                    $value = is_array($ids) ? ($ids[0] ?? null) : null;
                    break;
                }
            }
        }

        return is_string($value) ? self::assetUrl($site, $value) : null;
    }

    /**
     * The search island's data source for a collection, resolved at publish
     * by tier: static → flat JSON index; dynamic → the read-only public API.
     * The blocks don't know or care which tier feeds them.
     *
     * @return array{0: 'static'|'api', 1: string} [mode, url]
     */
    public static function searchSource(ContentCollection $collection, Site $site): array
    {
        if ($collection->tier === 'dynamic') {
            return ['api', rtrim((string) config('app.url'), '/') . "/api/v1/public/{$site->id}/collections/{$collection->slug}/records"];
        }

        return ['static', '/' . self::pathPrefix($collection) . '/index.json'];
    }

    /** HTML-safe rendering of one field value ('' when empty). */
    public static function display(Site $site, ContentCollection $collection, Record $record, string $fieldKey): string
    {
        $field = $collection->field($fieldKey);
        $value = $record->data[$fieldKey] ?? null;

        if (!$field) {
            return '';
        }

        if ($field['type'] === 'relation') {
            $edges = $record->relationsOut->where('relation_key', $fieldKey)->sortBy('position');
            $parts = [];
            foreach ($edges as $edge) {
                if ($edge->toRecord && $edge->toRecord->status === 'published') {
                    $target = ContentCollection::find($field['relation']['collection_id']);
                    $url = $target ? self::recordUrl($target, $edge->toRecord) : null;
                    $parts[] = $url
                        ? '<a href="' . e($url) . '">' . e($edge->toRecord->title) . '</a>'
                        : e($edge->toRecord->title);
                } elseif ($edge->toRecord) {
                    $parts[] = e($edge->toRecord->title);
                }
            }

            return implode(', ', $parts);
        }

        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($field['type']) {
            'price' => e(number_format((float) $value, 2)) . '&nbsp;' . e(self::currencySymbol($collection, $site)),
            'boolean' => $value ? '✓' : '—',
            'multi_select' => e(implode(', ', array_filter((array) $value, 'is_scalar'))),
            'date' => e(date('j M Y', strtotime((string) $value)) ?: (string) $value),
            'url' => '<a href="' . e((string) $value) . '" rel="noopener nofollow" target="_blank">' . e(parse_url((string) $value, PHP_URL_HOST) ?: (string) $value) . '</a>',
            'email' => '<a href="mailto:' . e((string) $value) . '">' . e((string) $value) . '</a>',
            'phone' => '<a href="tel:' . e(preg_replace('/[^+0-9]/', '', (string) $value)) . '">' . e((string) $value) . '</a>',
            'rich_text' => (string) $value, // purified at save — the one non-escaped path
            'image', 'file' => ($url = self::assetUrl($site, (string) $value))
                ? ($field['type'] === 'image'
                    ? '<img src="' . e($url) . '" alt="' . e($record->title ?? '') . '" loading="lazy">'
                    : '<a href="' . e($url) . '" rel="noopener">Download</a>')
                : '',
            'gallery' => implode('', array_map(
                fn ($id) => ($u = self::assetUrl($site, (string) $id)) ? '<img src="' . e($u) . '" alt="" loading="lazy">' : '',
                array_filter((array) $value, 'is_string'),
            )),
            'sku' => '<code>' . e((string) $value) . '</code>',
            default => e(is_scalar($value) ? (string) $value : ''),
        };
    }

    /** Plain-text value for search indexing / meta descriptions. */
    public static function plain(Record $record, array $field): string
    {
        $value = $record->data[$field['key']] ?? null;
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        if ($field['type'] === 'rich_text') {
            return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
        }
        if (is_array($value)) {
            return implode(' ', array_filter($value, 'is_scalar'));
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        return (string) $value;
    }
}
