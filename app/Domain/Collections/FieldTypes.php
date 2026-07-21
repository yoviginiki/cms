<?php

namespace App\Domain\Collections;

/**
 * The closed catalog of collection field types (Track G, v1 — resist
 * additions; the list is a scope wall). Everything that interprets a field —
 * schema validation, record validation, sanitization, entry-form generation,
 * search indexing, import mapping — keys off this catalog.
 */
final class FieldTypes
{
    public const TYPES = [
        'text',         // single-line string
        'rich_text',    // long/rich text, sanitized through HTMLPurifier
        'number',       // integer or float
        'price',        // decimal, currency from site settings
        'boolean',
        'select',       // one of defined options
        'multi_select', // many of defined options
        'date',         // Y-m-d
        'email',
        'url',          // http(s) only
        'phone',
        'image',        // asset ref (uuid)
        'gallery',      // asset refs (uuid[])
        'file',         // asset ref (uuid)
        'sku',          // part number: normalized string, unique-toggleable
        'relation',     // edge(s) to another collection, optional pivot fields
        'computed',     // v3: display-only rollup (count/sum over incoming relations)
    ];

    /** Types with no stored value — resolved at render/publish, never in data. */
    public const VIRTUAL_TYPES = ['computed'];

    /** Types whose value is an asset id (or array of ids for gallery). */
    public const ASSET_TYPES = ['image', 'gallery', 'file'];

    /** Types that require a defined options list. */
    public const OPTION_TYPES = ['select', 'multi_select'];

    /** Types allowed as pivot fields on a many-to-many relation (scalars only). */
    public const PIVOT_TYPES = ['text', 'number', 'price', 'boolean', 'select', 'date', 'sku'];

    /** Types the `unique` toggle may be set on. */
    public const UNIQUE_TYPES = ['text', 'sku', 'email', 'url', 'phone', 'number'];

    /**
     * Types whose values feed the search index / tsvector when `searchable`.
     * A searchable relation contributes its related records' TITLES — how
     * "search by author" works on a book.
     */
    public const SEARCHABLE_TYPES = [
        'text', 'rich_text', 'number', 'price', 'select', 'multi_select',
        'date', 'email', 'url', 'phone', 'sku', 'relation',
    ];

    /** Types that may become facets (filters) when `facetable`. */
    public const FACETABLE_TYPES = ['select', 'multi_select', 'boolean', 'relation'];

    /**
     * Reserved field keys. Deliberately minimal: data values are namespaced
     * under `data.*` everywhere (storage, API payloads, list sorting), so
     * 'title'/'slug'/'status' are legal field keys — the G-Q scoped SQL views
     * prefix system columns instead. Only keys that collide inside payload
     * shapes are blocked.
     */
    public const RESERVED_KEYS = ['id', 'data', 'relations', 'pivot', 'search_text'];

    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    /**
     * Canonical SKU/part-number normalization: uppercase, trimmed, internal
     * whitespace runs collapsed to a single space. Uniqueness and search
     * compare this stored form.
     */
    public static function normalizeSku(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}
