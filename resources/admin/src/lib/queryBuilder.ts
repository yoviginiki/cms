import type { Collection, CollectionField, SavedQueryCondition, SavedQueryGroup } from '@/lib/api';

/**
 * Track G-Q3 — client mirror of SavedQueryValidator's operator matrix.
 * The server re-validates everything; this only drives what the builder
 * offers so users can't compose a definition that will be rejected.
 */
export const OPERATORS: Record<string, { label: string; types: string[] | '*' }> = {
  eq: { label: 'is', types: ['text', 'sku', 'email', 'url', 'phone', 'select', 'number', 'price', 'date', 'boolean'] },
  neq: { label: 'is not', types: ['text', 'sku', 'email', 'url', 'phone', 'select', 'number', 'price', 'date', 'boolean'] },
  contains: { label: 'contains', types: ['text', 'sku', 'email', 'url', 'phone', 'rich_text'] },
  starts_with: { label: 'starts with', types: ['text', 'sku', 'email', 'url', 'phone'] },
  gt: { label: 'greater than', types: ['number', 'price', 'date'] },
  gte: { label: 'at least', types: ['number', 'price', 'date'] },
  lt: { label: 'less than', types: ['number', 'price', 'date'] },
  lte: { label: 'at most', types: ['number', 'price', 'date'] },
  between: { label: 'between', types: ['number', 'price', 'date'] },
  in: { label: 'is any of', types: ['select', 'text', 'sku'] },
  not_in: { label: 'is none of', types: ['select', 'text', 'sku'] },
  has_any: { label: 'has any of', types: ['multi_select'] },
  is_empty: { label: 'is empty', types: '*' },
  not_empty: { label: 'is not empty', types: '*' },
};

export const METRIC_FNS = ['count', 'sum', 'avg', 'min', 'max'] as const;
export const NUMERIC_TYPES = ['number', 'price'];
export const GROUPABLE_TYPES = ['select', 'boolean', 'relation'];
export const MAX_GROUP_DEPTH = 3;
export const MAX_SORT_KEYS = 3;
export const MAX_METRICS = 4;

export interface FieldPathOption {
  path: string;              // "price" or "supplier.lead_time"
  label: string;             // "Price" or "Supplier → Lead time"
  type: string;              // resolved field type
  options?: string[];        // select/multi_select choices
}

/**
 * Filterable field paths: every local non-relation field plus one hop into
 * each relation's target collection (the server's depth-2 wall).
 */
export function fieldPathOptions(collection: Collection, allCollections: Collection[]): FieldPathOption[] {
  const out: FieldPathOption[] = [];
  const fields: CollectionField[] = collection.schema?.fields ?? [];
  for (const f of fields) {
    if (f.type !== 'relation') {
      out.push({ path: f.key, label: f.label || f.key, type: f.type, options: f.options });
      continue;
    }
    const target = allCollections.find((c) => c.id === f.relation?.collection_id);
    for (const tf of target?.schema?.fields ?? []) {
      if (tf.type === 'relation') continue; // depth-2 wall
      out.push({
        path: `${f.key}.${tf.key}`,
        label: `${f.label || f.key} → ${tf.label || tf.key}`,
        type: tf.type,
        options: tf.options,
      });
    }
  }
  return out;
}

export function operatorsForType(type: string): { value: string; label: string }[] {
  return Object.entries(OPERATORS)
    .filter(([, meta]) => meta.types === '*' || meta.types.includes(type))
    .map(([value, meta]) => ({ value, label: meta.label }));
}

export function isGroup(node: SavedQueryCondition | SavedQueryGroup): node is SavedQueryGroup {
  return (node as SavedQueryGroup).children !== undefined;
}

export function emptyCondition(): SavedQueryCondition {
  return { field: '', operator: 'eq', value: '' };
}

export function emptyGroup(): SavedQueryGroup {
  return { op: 'and', children: [emptyCondition()] };
}

/** Strip UI scaffolding the server would reject (blank fields, empty groups). */
export function pruneGroup(group: SavedQueryGroup): SavedQueryGroup | null {
  const children = group.children
    .map((c) => (isGroup(c) ? pruneGroup(c) : c.field && c.operator ? c : null))
    .filter((c): c is SavedQueryCondition | SavedQueryGroup => c !== null);
  return children.length === 0 ? null : { op: group.op, children };
}
