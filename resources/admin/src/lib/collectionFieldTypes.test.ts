import { describe, expect, it } from 'vitest';
import {
  EDITABLE_SCALAR_TYPES, DEFAULTABLE_TYPES,
  flagDisabledReason, isEditableScalar, isSortableType, settingsForType, supportsDefault,
} from './collectionFieldTypes';

describe('computed field rules', () => {
  it('disables required/unique/searchable/facetable but allows show_in_list', () => {
    expect(flagDisabledReason('required', 'computed')).toBeTruthy();
    expect(flagDisabledReason('unique', 'computed')).toBeTruthy();
    expect(flagDisabledReason('searchable', 'computed')).toBeTruthy();
    expect(flagDisabledReason('facetable', 'computed')).toBeTruthy();
    expect(flagDisabledReason('show_in_list', 'computed')).toBeNull();
  });

  it('keeps existing flag rules for other types', () => {
    expect(flagDisabledReason('required', 'text')).toBeNull();
    expect(flagDisabledReason('unique', 'sku')).toBeNull();
    expect(flagDisabledReason('facetable', 'text')).toBeTruthy();
  });

  it('is neither sortable, inline-editable nor defaultable', () => {
    expect(isSortableType('computed')).toBe(false);
    expect(isEditableScalar('computed')).toBe(false);
    expect(supportsDefault('computed')).toBe(false);
  });
});

describe('isEditableScalar', () => {
  it('accepts exactly the editable scalar types', () => {
    for (const t of EDITABLE_SCALAR_TYPES) expect(isEditableScalar(t)).toBe(true);
    for (const t of ['rich_text', 'multi_select', 'image', 'gallery', 'file', 'relation', 'computed'] as const) {
      expect(isEditableScalar(t)).toBe(false);
    }
  });
});

describe('supportsDefault', () => {
  it('excludes asset, relation and computed types', () => {
    for (const t of DEFAULTABLE_TYPES) expect(supportsDefault(t)).toBe(true);
    for (const t of ['image', 'gallery', 'file', 'relation', 'computed'] as const) {
      expect(supportsDefault(t)).toBe(false);
    }
  });
});

describe('settingsForType', () => {
  it('offers pattern only on text and sku', () => {
    expect(settingsForType('text').pattern).toBe(true);
    expect(settingsForType('sku').pattern).toBe(true);
    expect(settingsForType('email').pattern).toBe(false);
    expect(settingsForType('number').pattern).toBe(false);
  });

  it('offers a date range only on date fields', () => {
    expect(settingsForType('date').dateRange).toBe(true);
    expect(settingsForType('text').dateRange).toBe(false);
  });
});
