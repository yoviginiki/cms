import type { GalleryImage } from '@/components/blocks/gallery/types';
import { normalizeGalleryImages } from '@/components/blocks/gallery/types';

/** Resolved, clamped options — mirrors the defaults/clamps in linear-gallery.blade.php. */
export type Shadow = 'none' | 'soft' | 'medium' | 'strong';
export type SizeVariation = 'none' | 'subtle' | 'strong';

export interface LinearGalleryOptions {
  images: GalleryImage[];
  height: number; mobileHeight: number; stripHeight: number;
  sizeVariation: SizeVariation; overlap: number; scatter: number;
  align: 'start' | 'center'; width: 'contained' | 'full';
  offsetStart: number; offsetEnd: number; padding: number; radius: number;
  blend: string; opacity: number; background: string;
  borderColor: string; borderWidth: number; shadow: Shadow;
  hoverBorderColor: string; hoverBorderWidth: number; hoverGlow: boolean; hoverShadow: Shadow; hoverLift: boolean;
  arrows: boolean; arrowsShow: 'hover' | 'always'; arrowsPosition: 'sides' | 'bottom-right' | 'bottom-center' | 'top-right';
  arrowsSize: 'sm' | 'md' | 'lg'; arrowsColor: string; arrowsBg: string; arrowsMobile: boolean;
  drag: boolean; scrollbar: boolean; autoplay: number; lightbox: boolean; openOn: 'click' | 'dblclick';
}

export const SHADOW_CSS: Record<Shadow, string> = {
  none: '0 0 0 0 transparent',
  soft: '0 6px 18px -6px rgba(0,0,0,.35)',
  medium: '0 14px 32px -10px rgba(0,0,0,.45)',
  strong: '0 24px 48px -12px rgba(0,0,0,.55)',
};
export const ARROW_PX = { sm: 36, md: 44, lg: 56 } as const;

const clamp = (v: unknown, def: number, min: number, max: number) => {
  const n = v === undefined || v === null || v === '' ? def : Number(v);
  return Math.max(min, Math.min(max, Number.isFinite(n) ? n : def));
};
const pick = <T extends string>(v: unknown, def: T, allowed: readonly T[]): T => (allowed.includes(v as T) ? (v as T) : def);
const bool = (v: unknown, def: boolean) => (v === undefined || v === null ? def : v !== false && v !== 0 && v !== '0');
const shadowOf = (v: unknown, def: Shadow): Shadow => (typeof v === 'boolean' ? (v ? 'strong' : 'none') : pick(v, def, ['none', 'soft', 'medium', 'strong'] as const));

export function resolveOptions(d: Record<string, unknown>): LinearGalleryOptions {
  return {
    images: normalizeGalleryImages(d.images),
    height: clamp(d.height, 360, 80, 1600),
    mobileHeight: clamp(d.mobileHeight, 220, 60, 1000),
    stripHeight: clamp(d.stripHeight, 0, 0, 2000),
    sizeVariation: pick(d.sizeVariation, 'strong', ['none', 'subtle', 'strong'] as const),
    overlap: clamp(d.overlap, 22, 0, 60),
    scatter: clamp(d.scatter, 24, 0, 60),
    align: pick(d.align, 'start', ['start', 'center'] as const),
    width: pick(d.width, 'contained', ['contained', 'full'] as const),
    offsetStart: clamp(d.offsetStart, 24, 0, 600),
    offsetEnd: clamp(d.offsetEnd, 24, 0, 600),
    padding: clamp(d.padding, 40, 0, 300),
    radius: clamp(d.radius, 3, 0, 60),
    blend: pick(d.blend, 'multiply', ['multiply', 'screen', 'darken', 'luminosity', 'normal'] as const),
    opacity: clamp(d.opacity, 92, 30, 100),
    background: (d.background as string) || '',
    borderColor: (d.borderColor as string) || '',
    borderWidth: clamp(d.borderWidth, 0, 0, 12),
    shadow: shadowOf(d.shadow, 'none'),
    hoverBorderColor: (d.hoverBorderColor as string) || '',
    hoverBorderWidth: clamp(d.hoverBorderWidth, 2, 0, 12),
    hoverGlow: bool(d.hoverGlow, true),
    hoverShadow: shadowOf(d.hoverShadow, 'strong'),
    hoverLift: bool(d.hoverLift, true),
    arrows: bool(d.arrows, true),
    arrowsShow: pick(d.arrowsShow, 'hover', ['hover', 'always'] as const),
    arrowsPosition: pick(d.arrowsPosition, 'sides', ['sides', 'bottom-right', 'bottom-center', 'top-right'] as const),
    arrowsSize: pick(d.arrowsSize, 'md', ['sm', 'md', 'lg'] as const),
    arrowsColor: (d.arrowsColor as string) || '',
    arrowsBg: (d.arrowsBg as string) || '',
    arrowsMobile: bool(d.arrowsMobile, false),
    drag: bool(d.drag, true),
    scrollbar: bool(d.scrollbar, false),
    autoplay: clamp(d.autoplay, 0, 0, 200),
    lightbox: bool(d.lightbox, true),
    openOn: pick(d.openOn, 'click', ['click', 'dblclick'] as const),
  };
}
