// ═══════════════════════════════════════════
// Magazine Editor Type System
// ═══════════════════════════════════════════

// ─── Element types ───
export type MagElementType =
  // Text frames
  | 'text_frame' | 'headline_frame' | 'pullquote_frame' | 'caption_frame'
  | 'footnote_frame' | 'marginalia_frame'
  // Image frames
  | 'image_frame' | 'circular_image' | 'polygon_image' | 'fullbleed_image'
  | 'gallery_frame' | 'background_image'
  // Shapes
  | 'rectangle' | 'ellipse' | 'line' | 'polygon' | 'freeform_path'
  | 'decorative_rule' | 'gradient_overlay'
  // Media
  | 'video_frame' | 'audio_player' | 'embed_frame' | 'svg_icon'
  // Interactive
  | 'button' | 'hotspot' | 'tooltip_trigger' | 'accordion_frame' | 'slidein_panel'
  // Data
  | 'table_frame' | 'chart_frame' | 'infographic_number' | 'progress_indicator'
  // Page structure
  | 'page_number' | 'running_header' | 'column_guides'
  // Grouping
  | 'group' | 'component_instance' | 'clipping_group';

export type MagElementCategory =
  | 'text' | 'image' | 'shape' | 'media' | 'interactive'
  | 'data' | 'page-structure' | 'grouping';

// ─── Core element ───
export interface MagElement {
  id: string;
  type: MagElementType;
  name: string | null;
  data: Record<string, unknown>;

  // Position & transform
  x: number;
  y: number;
  width: number;
  height: number;
  rotation: number;
  scaleX: number;
  scaleY: number;

  // Layer
  zIndex: number;
  locked: boolean;
  visible: boolean;
  layerName: string | null;

  // Styling
  style: MagElementStyle;
  typography: MagTypography | null;
  textWrap: MagTextWrap;

  // Threading
  threadId: string | null;
  threadOrder: number | null;

  // Page
  pageNumber: number;
  onMaster: boolean;

  // Hierarchy
  parentId: string | null;
  children: MagElement[];

  // Layout behavior (optional — defaults to free/page)
  positionMode?: 'free' | 'fixed';
  spanMode?: 'page' | 'spread';

  // Responsive
  responsiveOverrides: Record<string, unknown>;
}

// ─── Element style ───
export interface MagElementStyle {
  fill: {
    color: string | null;
    opacity: number;
    gradient: { type: 'linear' | 'radial'; angle: number; stops: Array<{ offset: number; color: string }> } | null;
  };
  stroke: {
    color: string;
    width: number;
    style: 'solid' | 'dashed' | 'dotted' | number[];
    alignment: 'inside' | 'center' | 'outside';
  };
  cornerRadius: { tl: number; tr: number; br: number; bl: number };
  opacity: number;
  shadow: { x: number; y: number; blur: number; spread: number; color: string } | null;
  innerShadow: { x: number; y: number; blur: number; color: string } | null;
  blendMode: 'normal' | 'multiply' | 'screen' | 'overlay' | 'darken' | 'lighten' | 'soft-light';
  blur: number;
}

// ─── Typography ───
export interface MagTypography {
  fontFamily: string;
  fontSize: number;
  fontWeight: number;
  fontStyle: 'normal' | 'italic';
  lineHeight: number;
  letterSpacing: number;
  wordSpacing: number;
  textAlign: 'left' | 'center' | 'right' | 'justify';
  textAlignLast: 'auto' | 'left' | 'center' | 'right';
  textTransform: 'none' | 'uppercase' | 'lowercase' | 'capitalize' | 'small-caps';
  textIndent: number;
  textColor: string;
  paragraphSpacingBefore: number;
  paragraphSpacingAfter: number;
  hyphenation: boolean;
  hangingPunctuation: boolean;
  opticalMarginAlignment: boolean;
  maxCharsPerLine: number | null;
  dropCap: { enabled: boolean; lines: number; font: string | null; color: string | null };
  openType: { ligatures: boolean; oldstyleNums: boolean; tabularNums: boolean; smallCaps: boolean; swashes: boolean };
  baselineShift: number;
  kerning: 'metrics' | 'optical' | number;
  orphans: number;
  widows: number;
  paragraphStyleId: string | null;
  characterStyleId: string | null;
}

// ─── Text wrap ───
export interface MagTextWrap {
  type: 'none' | 'bounding-box' | 'object-shape' | 'jump';
  offset: { top: number; right: number; bottom: number; left: number };
  side: 'both' | 'left' | 'right' | 'largest';
  customPath: string | null;
  invert: boolean;
}

// ─── Type-specific data interfaces ───
export interface TextFrameData {
  content: string;
  overflow: 'visible' | 'hidden' | 'threaded';
  autoSize: 'none' | 'grow-height' | 'shrink-text';
  columnsInFrame: number;
  columnGap: number;
  columnFill: 'auto' | 'balance';
  columnRule: boolean;
  textInset: { top: number; right: number; bottom: number; left: number };
  verticalAlign: 'top' | 'center' | 'bottom';
}

export interface ImageFrameData {
  assetId: string | null;
  alt: string;
  fit: 'fill' | 'fit' | 'stretch' | 'none';
  focalPoint: { x: number; y: number };
  imageOffsetX: number;
  imageOffsetY: number;
  imageScale: number;
  imageRotation: number;
  clipShape: 'rectangle' | 'ellipse' | 'polygon' | 'custom';
  clipPath: string | null;
  filters: { brightness: number; contrast: number; saturation: number; grayscale: boolean; duotone: { dark: string; light: string } | null };
}

export interface ShapeData {
  fillColor: string | null;
  fillGradient: { type: 'linear' | 'radial'; stops: Array<{ offset: number; color: string }>; angle: number } | null;
  canContainText: boolean;
  textContent: string | null;
  sides: number;
  innerRadius: number;
  cornerRadius: { tl: number; tr: number; br: number; bl: number };
}

export interface LineData {
  x2: number;
  y2: number;
  strokeWidth: number;
  strokeColor: string;
  strokeDash: 'solid' | 'dashed' | 'dotted' | number[];
  startCap: 'none' | 'arrow' | 'circle' | 'diamond' | 'square';
  endCap: 'none' | 'arrow' | 'circle' | 'diamond' | 'square';
}

export interface VideoData {
  url: string;
  posterAssetId: string | null;
  autoplay: false;
  aspectRatio: '16:9' | '4:3' | '1:1';
}

export interface ButtonData {
  text: string;
  url: string;
  variant: 'solid' | 'outline' | 'ghost';
  hoverColor: string | null;
}

export interface TableData {
  headers: string[];
  rows: string[][];
  headerStyle: string | null;
  cellStyle: string | null;
  stripes: boolean;
  borderColor: string;
}

export interface ChartData {
  chartType: 'bar' | 'line' | 'pie' | 'donut';
  data: Array<{ label: string; value: number; color: string | null }>;
  showLegend: boolean;
  animated: boolean;
}

export interface IconData {
  name: string;
  customSvg: string | null;
  color: string;
}

export interface GalleryData {
  images: Array<{ assetId: string; alt: string; caption: string | null; focalPoint: { x: number; y: number } }>;
  layout: 'grid' | 'masonry' | 'stack';
  columns: number;
  gap: number;
}

export interface HotspotData {
  action: 'url' | 'tooltip' | 'page-jump';
  url: string | null;
  tooltipContent: string | null;
  targetPage: number | null;
  cursorStyle: 'pointer' | 'help' | 'zoom-in';
}

export interface PageNumberData {
  format: 'decimal' | 'roman-lower' | 'roman-upper' | 'alpha-lower' | 'alpha-upper';
  prefix: string;
  suffix: string;
  startAt: number;
}

export interface RunningHeaderData {
  source: 'page-title' | 'section-name' | 'custom';
  customText: string | null;
}

export interface InfographicNumberData {
  value: string;
  label: string;
  prefix: string | null;
  suffix: string | null;
  animated: boolean;
}

export interface ComponentInstanceData {
  componentId: string;
  overrides: Record<string, unknown>;
}

// ─── Page ───
export interface MagPageData {
  id: string;
  pageNumber: number;
  pageSize: { width: number; height: number };
  margins: { top: number; right: number; bottom: number; left: number };
  bleed: { top: number; right: number; bottom: number; left: number };
  columns: { count: number; gutter: number };
  baselineGrid: { increment: number; start: number };
  isMaster: boolean;
  masterPageId: string | null;
  spreadWith: number | null;
  backgroundColor: string | null;
  backgroundAssetId: string | null;
  elements: MagElement[];
}

// ─── Style definitions ───
export interface MagStyleDefinition {
  id: string;
  name: string;
  type: 'paragraph' | 'character';
  properties: Partial<MagTypography>;
  basedOnId: string | null;
  nextStyleId: string | null;
  isDefault: boolean;
}

// ─── Element definition (for element palette) ───
export interface MagElementDefinition {
  type: MagElementType;
  category: MagElementCategory;
  label: string;
  icon: string;
  description: string;
  defaultWidth: number;
  defaultHeight: number;
  hasTypography: boolean;
  hasTextWrap: boolean;
  canContainText: boolean;
  canContainChildren: boolean;
  tier: 'mvp' | 'v2' | 'v3';
}

// ─── Default element style ───
export const DEFAULT_ELEMENT_STYLE: MagElementStyle = {
  fill: { color: null, opacity: 1, gradient: null },
  stroke: { color: 'transparent', width: 0, style: 'solid', alignment: 'center' },
  cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 },
  opacity: 1,
  shadow: null,
  innerShadow: null,
  blendMode: 'normal',
  blur: 0,
};

export const DEFAULT_TEXT_WRAP: MagTextWrap = {
  type: 'none',
  offset: { top: 0, right: 0, bottom: 0, left: 0 },
  side: 'both',
  customPath: null,
  invert: false,
};

export const DEFAULT_TYPOGRAPHY: MagTypography = {
  fontFamily: 'Inter',
  fontSize: 14,
  fontWeight: 400,
  fontStyle: 'normal',
  lineHeight: 1.5,
  letterSpacing: 0,
  wordSpacing: 0,
  textAlign: 'left',
  textAlignLast: 'auto',
  textTransform: 'none',
  textIndent: 0,
  textColor: '#1a1a1a',
  paragraphSpacingBefore: 0,
  paragraphSpacingAfter: 12,
  hyphenation: false,
  hangingPunctuation: false,
  opticalMarginAlignment: false,
  maxCharsPerLine: null,
  dropCap: { enabled: false, lines: 3, font: null, color: null },
  openType: { ligatures: true, oldstyleNums: false, tabularNums: false, smallCaps: false, swashes: false },
  baselineShift: 0,
  kerning: 'metrics',
  orphans: 2,
  widows: 2,
  paragraphStyleId: null,
  characterStyleId: null,
};
