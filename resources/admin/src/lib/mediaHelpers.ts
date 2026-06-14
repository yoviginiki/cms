/**
 * Sprint 8 — Media and image metadata helpers.
 */

export interface ImageMetadata {
  altText: string;
  caption: string;
  title: string;
  width: number | null;
  height: number | null;
  mimeType: string;
  fileSize: number;
  focalPointX: number;
  focalPointY: number;
}

const DEFAULTS: ImageMetadata = {
  altText: '',
  caption: '',
  title: '',
  width: null,
  height: null,
  mimeType: '',
  fileSize: 0,
  focalPointX: 50,
  focalPointY: 50,
};

/** Normalize raw asset data to ImageMetadata with safe defaults */
export function normalizeImageMetadata(raw: any): ImageMetadata {
  if (!raw || typeof raw !== 'object') return { ...DEFAULTS };
  return {
    altText: typeof raw.alt_text === 'string' ? raw.alt_text : (typeof raw.altText === 'string' ? raw.altText : ''),
    caption: typeof raw.caption === 'string' ? raw.caption : '',
    title: typeof raw.title === 'string' ? raw.title : (typeof raw.original_name === 'string' ? raw.original_name : ''),
    width: raw.dimensions?.width ?? raw.width ?? null,
    height: raw.dimensions?.height ?? raw.height ?? null,
    mimeType: typeof raw.mime_type === 'string' ? raw.mime_type : '',
    fileSize: typeof raw.file_size === 'number' ? raw.file_size : (typeof raw.size === 'number' ? raw.size : 0),
    focalPointX: clamp(raw.focal_point_x ?? raw.focalPointX ?? 50, 0, 100),
    focalPointY: clamp(raw.focal_point_y ?? raw.focalPointY ?? 50, 0, 100),
  };
}

/** Check if an image is missing alt text */
export function isAltTextMissing(asset: any): boolean {
  const alt = asset?.alt_text ?? asset?.altText ?? '';
  return typeof alt === 'string' && alt.trim().length === 0;
}

/** Generate alt text warning message */
export function altTextWarning(asset: any): string | null {
  if (!asset) return null;
  if (isAltTextMissing(asset)) return 'Missing alt text — important for accessibility and SEO';
  return null;
}

/** Format file size for display */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}

/** Format image dimensions */
export function formatDimensions(w: number | null, h: number | null): string {
  if (!w || !h) return 'Unknown';
  return `${w} × ${h}`;
}

/** Generate CSS object-position from focal point */
export function focalPointToObjectPosition(x: number, y: number): string {
  return `${clamp(x, 0, 100)}% ${clamp(y, 0, 100)}%`;
}

/** Generate CSS background-position from focal point */
export function focalPointToBackgroundPosition(x: number, y: number): string {
  return `${clamp(x, 0, 100)}% ${clamp(y, 0, 100)}%`;
}

/** Check if mime type is an image */
export function isImageMimeType(mimeType: string): boolean {
  return typeof mimeType === 'string' && mimeType.startsWith('image/');
}

function clamp(val: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, Number(val) || min));
}
