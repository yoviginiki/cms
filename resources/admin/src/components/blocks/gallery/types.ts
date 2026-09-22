/** One gallery image. Legacy galleries stored bare URL strings; `normalizeGalleryImages` upgrades them. */
export interface GalleryImage {
  id?: string;
  src: string;
  alt?: string;
  caption?: string;
  link?: string;
  width?: number | null;
  height?: number | null;
}

export type GalleryAspect = 'square' | '4:3' | '3:2' | '16:9' | 'natural';

export interface GalleryData {
  images: GalleryImage[];
  layout: 'grid' | 'masonry' | 'carousel';
  columns: number;
  gap: string;
  aspect: GalleryAspect;
  lightbox: boolean;
  captions: boolean;
}

export function normalizeGalleryImages(raw: unknown): GalleryImage[] {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((item): GalleryImage | null => {
      if (typeof item === 'string') return item.trim() ? { src: item.trim() } : null;
      if (item && typeof item === 'object') {
        const o = item as Record<string, unknown>;
        const src = String(o.src ?? o.url ?? '').trim();
        return src ? { ...o, src } as GalleryImage : null;
      }
      return null;
    })
    .filter((x): x is GalleryImage => x !== null);
}

export const ASPECT_RATIO: Record<GalleryAspect, string | undefined> = {
  square: '1 / 1', '4:3': '4 / 3', '3:2': '3 / 2', '16:9': '16 / 9', natural: undefined,
};
