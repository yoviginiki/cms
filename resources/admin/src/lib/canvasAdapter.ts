// Lossless mapping between the normal block tree (BlockData[]) and the canvas
// editor model (CanvasDoc). Canvas has NO separate storage: sections are
// `section` blocks, elements are their child blocks carrying style.layout.
//
// blockToCanvas(canvasToBlocks(doc)) === doc, and canvasToBlocks(blockToCanvas
// (blocks)) === blocks for canvas-shaped input — pinned by canvasAdapter.test.ts.

import type { BlockData } from '@/types/blocks';
import type { CanvasDoc, CanvasElement, CanvasPageType, CanvasSection } from '@/types/canvas';
import { DEFAULT_CANVAS_WIDTH } from '@/types/canvas';

const px = (v: unknown, def = 0): number => {
  if (typeof v === 'number') return v;
  const n = parseFloat(String(v ?? ''));
  return Number.isFinite(n) ? n : def;
};

function newId(): string {
  // crypto.randomUUID is available in the admin runtime.
  return typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.round(Math.random() * 1e9)}`;
}

// ── BlockData → canvas ──────────────────────────────────────────────────────

function blockToElement(block: BlockData): CanvasElement {
  const styleIn = (block.style ?? {}) as Record<string, unknown>;
  const layout = (styleIn.layout ?? {}) as Record<string, unknown>;
  // Split the position keys (represented as element fields) from any residual
  // layout props (maxWidth, alignment, …) which are carried verbatim.
  const { x, y, width, height, rotation, zIndex, position, locked, ...restLayout } = layout;
  const style: Record<string, unknown> = { ...styleIn };
  if (Object.keys(restLayout).length) style.layout = restLayout;
  else delete style.layout;

  const presetId = (block as { preset_id?: string | null }).preset_id;
  return {
    id: block.id,
    blockType: block.type,
    data: block.data ?? {},
    x: px(x, 0),
    y: px(y, 0),
    width: px(width, 200),
    height: px(height, 100),
    rotation: px(rotation, 0),
    zIndex: typeof zIndex === 'number' ? zIndex : 0,
    locked: locked === true,
    style,
    ...(block.animation ? { animation: block.animation as Record<string, unknown> } : {}),
    ...(block.responsive ? { responsive: block.responsive as Record<string, unknown> } : {}),
    ...(block.advanced ? { advanced: block.advanced as Record<string, unknown> } : {}),
    ...(presetId ? { preset_id: presetId } : {}),
  };
}

function blockToSection(block: BlockData): CanvasSection {
  const dataIn = (block.data ?? {}) as Record<string, unknown>;
  const { canvas: canvasRaw, ...restData } = dataIn;
  const canvas = (canvasRaw ?? {}) as Record<string, unknown>;
  const height = canvas.height === 'auto' || canvas.height == null ? 'auto' : px(canvas.height, 480);
  return {
    id: block.id,
    settings: {
      height,
      bleed: canvas.bleed === true,
      background: typeof canvas.background === 'string' ? canvas.background : '',
    },
    data: restData,
    style: (block.style ?? {}) as Record<string, unknown>,
    elements: (block.children ?? []).map(blockToElement),
  };
}

export function blockToCanvas(
  blocks: BlockData[],
  meta: { pageType?: CanvasPageType; width?: number } = {},
): CanvasDoc {
  return {
    pageType: meta.pageType === 'single' ? 'single' : 'website',
    width: meta.width && meta.width > 0 ? meta.width : DEFAULT_CANVAS_WIDTH,
    // Only `section`-typed top-level blocks are canvas sections; anything else
    // is ignored by the canvas view (shouldn't occur for canvas pages).
    sections: blocks.filter(b => b.type === 'section').map(blockToSection),
  };
}

// ── canvas → BlockData ──────────────────────────────────────────────────────

function elementToBlock(el: CanvasElement, order: number): BlockData {
  const style = { ...(el.style ?? {}) } as Record<string, unknown>;
  const prevLayout = (style.layout ?? {}) as Record<string, unknown>;
  style.layout = {
    ...prevLayout,
    position: 'absolute',
    x: el.x,
    y: el.y,
    width: `${el.width}px`,
    height: `${el.height}px`,
    rotation: el.rotation,
    zIndex: el.zIndex,
    locked: el.locked,
  };
  return {
    id: el.id,
    type: el.blockType,
    data: el.data ?? {},
    children: [],
    order,
    style,
    ...(el.animation ? { animation: el.animation } : {}),
    ...(el.responsive ? { responsive: el.responsive } : {}),
    ...(el.advanced ? { advanced: el.advanced } : {}),
    ...(el.preset_id ? { preset_id: el.preset_id } : {}),
  } as BlockData;
}

function sectionToBlock(section: CanvasSection, order: number): BlockData {
  return {
    id: section.id,
    type: 'section',
    level: 'section',
    data: {
      ...section.data,
      canvas: {
        height: section.settings.height,
        bleed: section.settings.bleed,
        background: section.settings.background,
      },
    },
    style: section.style ?? {},
    children: section.elements.map((el, i) => elementToBlock(el, i)),
    order,
  } as BlockData;
}

export function canvasToBlocks(doc: CanvasDoc): BlockData[] {
  return doc.sections.map((s, i) => sectionToBlock(s, i));
}

// ── factories ───────────────────────────────────────────────────────────────

export function createSection(settings?: Partial<CanvasSection['settings']>): CanvasSection {
  return {
    id: newId(),
    settings: { height: 480, bleed: false, background: '', ...settings },
    data: {},
    style: {},
    elements: [],
  };
}

export function createElement(blockType: string, x: number, y: number, width = 240, height = 120): CanvasElement {
  return {
    id: newId(),
    blockType,
    data: {},
    x, y, width, height,
    rotation: 0,
    zIndex: 1,
    locked: false,
    style: {},
  };
}
