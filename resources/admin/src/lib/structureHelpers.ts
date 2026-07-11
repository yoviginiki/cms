import type { BlockData } from '@/types/blocks';

export type DropZone = 'before' | 'after' | 'inside';

/**
 * Content-derived label for a Structure-tree row. A custom `data.__label`
 * always wins; otherwise derive from the block's content. Returns null when
 * nothing content-specific applies, so the caller can fall back to the
 * block-type label from the registry.
 */
export function structureLabel(block: BlockData): string | null {
  const custom = (block.data?.__label as string | undefined)?.trim();
  if (custom) return custom;

  const d = (block.data ?? {}) as Record<string, unknown>;
  if (block.type === 'heading' && d.text) return String(d.text).slice(0, 40);
  if (block.type === 'text' && d.content) {
    const s = String(d.content).replace(/<[^>]+>/g, '').trim();
    if (s) return s.slice(0, 40);
  }
  if (block.type === 'button' && (d.text || d.label)) return String(d.text ?? d.label).slice(0, 40);
  if (block.type === 'image' && (d.url || d.src)) return 'Image';
  if (block.type === 'row' && d.layout) return `Row · ${String(d.layout)}`;
  return null;
}

/**
 * Which drop zone a pointer is over within a tree row: the middle band nests
 * (only when the target can hold children), the top/bottom halves insert as a
 * sibling before/after.
 */
export function dropZone(offsetY: number, rowHeight: number, targetAllowsChildren: boolean): DropZone {
  if (targetAllowsChildren && offsetY > rowHeight * 0.25 && offsetY < rowHeight * 0.75) return 'inside';
  return offsetY < rowHeight / 2 ? 'before' : 'after';
}
