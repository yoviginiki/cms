// Which blocks the canvas palette offers, their starting size when placed, and
// the drag-and-drop contract between the palette and a section canvas.

/**
 * The curated canvas palette: galleries and the basics, grouped for the palette
 * UI. Deliberately small — every block here is verified to render in the editor
 * (canvasBlocks.render.test.tsx) and to publish inside a canvas section
 * (CanvasPaletteBlocksTest.php). Structural (section/row/column/grid),
 * post/collection-bound and page-chrome blocks are left out on purpose.
 */
export const CANVAS_BLOCK_GROUPS: Array<{ title: string; types: string[] }> = [
  { title: 'Text', types: ['heading', 'text', 'paragraph', 'pullquote', 'list'] },
  { title: 'Media', types: ['image', 'imagecaption', 'gallery', 'linear-gallery', 'logostrip', 'beforeafter', 'video', 'audio', 'icon'] },
  { title: 'Elements', types: ['button', 'divider', 'shape', 'testimonial', 'stats', 'map', 'socialembed', 'html-embed'] },
];

/** Flat list of every palette block type, in palette order. */
export const CANVAS_BLOCKS: string[] = CANVAS_BLOCK_GROUPS.flatMap(g => g.types);

/** dataTransfer MIME type carrying the block type while dragging from the palette. */
export const CANVAS_DRAG_MIME = 'application/x-canvas-block';

const SIZES: Record<string, [number, number]> = {
  heading: [420, 64],
  text: [320, 120],
  paragraph: [320, 120],
  pullquote: [420, 140],
  list: [320, 160],
  image: [320, 240],
  imagecaption: [320, 280],
  gallery: [480, 320],
  'linear-gallery': [640, 320],
  logostrip: [640, 100],
  beforeafter: [480, 320],
  video: [480, 270],
  audio: [320, 64],
  icon: [64, 64],
  button: [180, 48],
  divider: [320, 12],
  shape: [160, 160],
  testimonial: [420, 200],
  stats: [480, 140],
  map: [480, 320],
  socialembed: [400, 400],
  'html-embed': [320, 200],
};

/** Starting width/height for a freshly placed block. */
export function defaultSize(blockType: string): { width: number; height: number } {
  const [width, height] = SIZES[blockType] ?? [260, 120];
  return { width, height };
}

/**
 * Where a palette drop lands: the block is centred on the pointer, expressed in
 * canvas (design-width) px — the canvas is CSS-scaled by `zoom`, so screen
 * offsets are divided back out — and kept inside the canvas' left/top edges.
 */
export function dropPosition(
  clientX: number,
  clientY: number,
  canvasRect: { left: number; top: number },
  zoom: number,
  size: { width: number; height: number },
): { x: number; y: number } {
  const z = zoom || 1;
  const px = (clientX - canvasRect.left) / z;
  const py = (clientY - canvasRect.top) / z;
  return {
    x: Math.max(0, Math.round(px - size.width / 2)),
    y: Math.max(0, Math.round(py - size.height / 2)),
  };
}
