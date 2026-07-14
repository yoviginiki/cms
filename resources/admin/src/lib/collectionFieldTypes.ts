import type { LucideIcon } from 'lucide-react';
import {
  Type, AlignLeft, Hash, Banknote, ToggleLeft, Calendar, ListFilter, ListChecks,
  Image, Images, File, Mail, Link as LinkIcon, Phone, Barcode, Share2,
} from 'lucide-react';
import type { CollectionFieldType, CollectionField, CollectionPivotFieldType } from '@/lib/api';

/** Per-type UI metadata for the schema builder and record editor. */
export const FIELD_TYPE_META: Record<CollectionFieldType, { label: string; hint: string; icon: LucideIcon }> = {
  text:         { label: 'Text',         hint: 'Single-line text',                        icon: Type },
  rich_text:    { label: 'Rich text',    hint: 'Formatted long-form content',             icon: AlignLeft },
  number:       { label: 'Number',       hint: 'Integer or decimal value',                icon: Hash },
  price:        { label: 'Price',        hint: 'Money amount, 2 decimals',                icon: Banknote },
  boolean:      { label: 'Toggle',       hint: 'Yes / no switch',                         icon: ToggleLeft },
  date:         { label: 'Date',         hint: 'Calendar date',                           icon: Calendar },
  select:       { label: 'Select',       hint: 'One value from a fixed list',             icon: ListFilter },
  multi_select: { label: 'Multi-select', hint: 'Several values from a fixed list',        icon: ListChecks },
  image:        { label: 'Image',        hint: 'One image from the media library',        icon: Image },
  gallery:      { label: 'Gallery',      hint: 'Ordered set of images',                   icon: Images },
  file:         { label: 'File',         hint: 'Downloadable file (PDF, zip, …)',         icon: File },
  email:        { label: 'Email',        hint: 'Validated email address',                 icon: Mail },
  url:          { label: 'URL',          hint: 'Validated web address',                   icon: LinkIcon },
  phone:        { label: 'Phone',        hint: 'Phone number',                            icon: Phone },
  sku:          { label: 'SKU',          hint: 'Product code, stored uppercase',          icon: Barcode },
  relation:     { label: 'Relation',     hint: 'Link records from another collection',    icon: Share2 },
};

/** Type picker groups (schema builder add-field flow). */
export const FIELD_TYPE_GROUPS: { group: string; types: CollectionFieldType[] }[] = [
  { group: 'Basics',   types: ['text', 'rich_text', 'number', 'price', 'boolean', 'date'] },
  { group: 'Choices',  types: ['select', 'multi_select'] },
  { group: 'Media',    types: ['image', 'gallery', 'file'] },
  { group: 'Advanced', types: ['email', 'url', 'phone', 'sku', 'relation'] },
];

const UNIQUE_TYPES: CollectionFieldType[] = ['text', 'sku', 'email', 'url', 'phone', 'number'];
const NOT_SEARCHABLE_TYPES: CollectionFieldType[] = ['boolean', 'image', 'gallery', 'file', 'relation'];
const FACETABLE_TYPES: CollectionFieldType[] = ['select', 'multi_select', 'boolean', 'relation'];

export type FieldFlag = 'required' | 'unique' | 'searchable' | 'facetable' | 'show_in_list';

/** Returns null when a flag is allowed for the type; otherwise a human reason for the disabled toggle. */
export function flagDisabledReason(flag: FieldFlag, type: CollectionFieldType): string | null {
  switch (flag) {
    case 'unique':
      return UNIQUE_TYPES.includes(type) ? null : 'Uniqueness is only enforceable for text, SKU, email, URL, phone and number fields';
    case 'searchable':
      return NOT_SEARCHABLE_TYPES.includes(type) ? `${FIELD_TYPE_META[type].label} fields can't feed full-text search` : null;
    case 'facetable':
      return FACETABLE_TYPES.includes(type) ? null : 'Facets need a small fixed set of values — only select, multi-select, toggle and relation fields qualify';
    default:
      return null;
  }
}

export const PIVOT_FIELD_TYPES: CollectionPivotFieldType[] = ['text', 'number', 'price', 'boolean', 'select', 'date', 'sku'];

export const FIELD_KEY_REGEX = /^[a-z][a-z0-9_]{0,39}$/;
export const RESERVED_FIELD_KEYS = ['id', 'data', 'relations', 'pivot', 'search_text'];

/** Suggest a valid field key from a human label ("Release Date" → "release_date"). */
export function keyFromLabel(label: string): string {
  const key = label
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .replace(/^[^a-z]+/, '')
    .slice(0, 40);
  return key;
}

/** Client-side key validation mirroring the backend rules. Returns an error message or null. */
export function fieldKeyError(key: string, otherKeys: string[]): string | null {
  if (!key) return 'Key is required';
  if (!FIELD_KEY_REGEX.test(key)) return 'Lowercase letter first, then lowercase letters, digits or underscores (max 40 chars)';
  if (RESERVED_FIELD_KEYS.includes(key)) return `"${key}" is a reserved key`;
  if (otherKeys.includes(key)) return 'Another field already uses this key';
  return null;
}

/** Field types that may act as the record title / slug source. */
export function isTitleCandidate(f: CollectionField): boolean {
  return f.type === 'text' || f.type === 'sku';
}

/** Which of the optional per-field settings apply to a type. */
export function settingsForType(type: CollectionFieldType): { maxLength: boolean; range: boolean; rows: boolean; placeholder: boolean } {
  const textLike = ['text', 'email', 'url', 'phone', 'sku'].includes(type);
  return {
    maxLength: textLike,
    range: type === 'number' || type === 'price',
    rows: type === 'rich_text',
    placeholder: textLike || type === 'number' || type === 'price' || type === 'rich_text',
  };
}

/** Columns of these types can be sorted server-side via sort=data.{key}. */
export function isSortableType(type: CollectionFieldType): boolean {
  return !['image', 'gallery', 'file', 'relation', 'multi_select', 'rich_text'].includes(type);
}
