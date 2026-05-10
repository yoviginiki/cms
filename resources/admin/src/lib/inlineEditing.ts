/**
 * Inline Editing Contract
 *
 * General-purpose types for declaring which block fields support inline
 * editing on the editor canvas.  Every block that wants inline editing
 * imports these types and exposes an `inlineEditing` config alongside its
 * BlockDefinition.
 *
 * This is a CMS-wide foundation — not specific to any single block.
 */

// ── Field type enum ─────────────────────────────────────────────────────
/**
 * The kind of inline editor rendered on the canvas.
 *
 * - `text`      — single-line plain text (Enter commits)
 * - `multiline` — multi-line plain text (Shift+Enter for newline, Enter commits)
 *
 * Future additions (not yet implemented):
 * - `richtext`  — formatted text via TipTap or similar
 */
export type InlineEditableFieldType = 'text' | 'multiline';

// ── Single field descriptor ─────────────────────────────────────────────
/**
 * Describes one inline-editable field inside a block.
 *
 * `key` MUST match the corresponding key in the block's `defaultData` and
 * the side-panel Editor field — both edit the same `block.data[key]`.
 */
export interface InlineEditableField {
  /** Data key in block.data — must match definition.defaultData key exactly. */
  key: string;
  /** Human-readable label (used for aria-label and documentation). */
  label: string;
  /** Editor type rendered on the canvas. */
  type: InlineEditableFieldType;
  /** Placeholder text shown when the field is empty. */
  placeholder: string;
  /**
   * Whether the same field also appears in the right-side settings panel.
   * Should be `true` for all inline-editable fields (the panel is the
   * fallback editing surface).
   */
  panelFallback: boolean;
  /**
   * HTML tag rendered by InlineTextField.
   * Drives semantic rendering in the canvas preview.
   * Default: 'span'
   */
  as?: 'span' | 'p' | 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6' | 'div';
}

// ── Per-block config ────────────────────────────────────────────────────
/**
 * Inline editing configuration for a single block type.
 *
 * Blocks expose this alongside their BlockDefinition so the editor knows
 * which fields can be edited directly on the canvas.
 */
export interface InlineEditingConfig {
  /** Block type identifier — must match BlockDefinition.type. */
  blockType: string;
  /** Ordered list of inline-editable fields for this block. */
  fields: InlineEditableField[];
}

// ── Helper: build a field descriptor with defaults ──────────────────────
/**
 * Convenience factory for creating an InlineEditableField with sensible
 * defaults (`type: 'text'`, `panelFallback: true`, `as: 'span'`).
 */
export function defineInlineField(
  field: Pick<InlineEditableField, 'key' | 'label' | 'placeholder'> &
    Partial<Omit<InlineEditableField, 'key' | 'label' | 'placeholder'>>,
): InlineEditableField {
  return {
    type: 'text',
    panelFallback: true,
    as: 'span',
    ...field,
  };
}
