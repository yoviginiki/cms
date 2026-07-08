// Canvas editor model. A canvas page/post is a vertical stack of Section
// canvases; each section holds freeform-positioned website blocks. This maps
// losslessly to/from the normal block tree (BlockData) via lib/canvasAdapter —
// there is NO separate storage: sections are `section` blocks, elements are
// their child blocks carrying style.layout {x,y,width,height,rotation,zIndex}.

export type CanvasPageType = 'website' | 'single';

// Editor breakpoints. `desktop` is the base layout (style.layout.{x,y,…});
// `mobile` is an optional per-element override stored under layout.bp.mobile.
export type Breakpoint = 'desktop' | 'mobile';

export const MOBILE_MAX = 767;              // px — phone media-query threshold
export const DEFAULT_MOBILE_WIDTH = 390;    // px — mobile design canvas width

/** A partial position override for a breakpoint; missing keys inherit the base. */
export interface BreakpointLayout {
  x?: number;
  y?: number;
  width?: number;
  height?: number;
  rotation?: number;
  zIndex?: number;
  hidden?: boolean;
}

export interface CanvasElement {
  id: string;
  blockType: string;
  data: Record<string, unknown>;
  // Freeform position in px on the design-width (desktop) canvas.
  x: number;
  y: number;
  width: number;
  height: number;
  rotation: number;
  zIndex: number;
  locked: boolean;
  // Per-breakpoint position overrides (currently just `mobile`).
  bp?: { mobile?: BreakpointLayout };
  // Everything else on the block, carried verbatim so save is lossless.
  style: Record<string, unknown>;
  animation?: Record<string, unknown>;
  responsive?: Record<string, unknown>;
  advanced?: Record<string, unknown>;
  preset_id?: string | null;
}

/** The effective position of an element at a given breakpoint. */
export interface EffectiveLayout {
  x: number; y: number; width: number; height: number; rotation: number; zIndex: number; hidden: boolean;
}

export function effectiveLayout(el: CanvasElement, bp: Breakpoint): EffectiveLayout {
  const base = { x: el.x, y: el.y, width: el.width, height: el.height, rotation: el.rotation, zIndex: el.zIndex, hidden: false };
  if (bp === 'mobile' && el.bp?.mobile) return { ...base, ...el.bp.mobile } as EffectiveLayout;
  return base;
}

export interface CanvasSectionSettings {
  height: number | 'auto';
  bleed: boolean;
  background: string;
}

export interface CanvasSection {
  id: string;
  settings: CanvasSectionSettings;
  // The section block's other data (background/padding/etc), carried verbatim.
  data: Record<string, unknown>;
  style: Record<string, unknown>;
  elements: CanvasElement[];
}

export interface CanvasDoc {
  pageType: CanvasPageType;
  width: number; // design width (px) — element coords are relative to this
  sections: CanvasSection[];
}

export const DEFAULT_CANVAS_WIDTH = 1200;

export const DEFAULT_SECTION_SETTINGS: CanvasSectionSettings = {
  height: 480,
  bleed: false,
  background: '',
};
