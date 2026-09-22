import type { GalleryImage } from '@/components/blocks/gallery/types';

/** Mirrors the deterministic sequences in resources/views/blocks/linear-gallery.blade.php. */
export type SizeVariation = 'none' | 'subtle' | 'strong';

const SCALES: Record<SizeVariation, number[]> = {
  none: [1],
  subtle: [1, 0.88, 0.94, 0.82, 1, 0.9, 0.86],
  strong: [1, 0.72, 0.9, 0.58, 0.96, 0.66, 0.84, 0.52, 0.92, 0.7],
};
const DY = [0, 0.55, -0.45, 0.85, -0.2, 0.35, -0.7, 0.5, -0.35, 0.25];
const Z = [3, 1, 4, 2, 5, 1, 3, 2, 4, 1];

export interface ComposedImage extends GalleryImage { scale: number; dy: number; z: number }

export function compose(images: GalleryImage[], variation: SizeVariation, scatterPct: number): ComposedImage[] {
  const scales = SCALES[variation] ?? SCALES.strong;
  return images.map((img, i) => ({
    ...img,
    scale: scales[i % scales.length],
    dy: DY[i % DY.length] * (scatterPct / 100),
    z: Z[i % Z.length],
  }));
}
