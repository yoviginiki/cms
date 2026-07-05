/**
 * Deterministic wireframe geometry for flatplan thumbnails — one schematic
 * sketch per pattern in resources/playbook/spread-patterns.md. NOT AI output:
 * these are hand-authored abstractions of each pattern's grid (where image
 * mass sits, where text sits). Spread canvas is 1190x842 (two A4 pages),
 * covers are a single 595x842 page.
 */

export type BlockKind = 'img' | 'txt' | 'hl' | 'q' | 'tbl' | 'rule' | 'vrule' | 'box' | 'cap';

export interface SketchBlock {
  k: BlockKind;
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface PatternSketch {
  cover: boolean; // single page instead of spread
  blocks: SketchBlock[];
}

const P = 595; // page width
const S = 1190; // spread width

export const PATTERN_SKETCHES: Record<string, PatternSketch> = {
  'cover-image': {
    cover: true,
    blocks: [
      { k: 'img', x: 0, y: 0, w: P, h: 842 },
      { k: 'hl', x: 50, y: 560, w: 420, h: 70 },
      { k: 'txt', x: 50, y: 660, w: 260, h: 70 },
      { k: 'cap', x: 50, y: 60, w: 180, h: 50 },
    ],
  },
  'cover-type': {
    cover: true,
    blocks: [
      { k: 'hl', x: 60, y: 190, w: 475, h: 200 },
      { k: 'txt', x: 60, y: 440, w: 320, h: 40 },
      { k: 'rule', x: 60, y: 520, w: 380, h: 4 },
      { k: 'img', x: 60, y: 620, w: 140, h: 140 },
    ],
  },
  'full-bleed-opener': {
    cover: false,
    blocks: [
      { k: 'img', x: 0, y: 0, w: S, h: 842 },
      { k: 'hl', x: 80, y: 110, w: 430, h: 80 },
      { k: 'txt', x: 80, y: 220, w: 300, h: 80 },
      { k: 'cap', x: 60, y: 770, w: 210, h: 22 },
    ],
  },
  'poster-type-opener': {
    cover: false,
    blocks: [
      { k: 'hl', x: 60, y: 80, w: 480, h: 300 },
      { k: 'txt', x: 60, y: 630, w: 300, h: 100 },
      { k: 'txt', x: 650, y: 60, w: 240, h: 720 },
      { k: 'txt', x: 910, y: 60, w: 240, h: 720 },
    ],
  },
  'portrait-profile': {
    cover: false,
    blocks: [
      { k: 'img', x: 0, y: 0, w: P, h: 842 },
      { k: 'cap', x: 30, y: 786, w: 180, h: 20 },
      { k: 'hl', x: 645, y: 90, w: 430, h: 90 },
      { k: 'txt', x: 645, y: 220, w: 340, h: 70 },
      { k: 'txt', x: 645, y: 330, w: 250, h: 450 },
    ],
  },
  'text-well-two-column': {
    cover: false,
    blocks: [
      { k: 'txt', x: 105, y: 60, w: 185, h: 720 },
      { k: 'txt', x: 310, y: 60, w: 185, h: 720 },
      { k: 'txt', x: 700, y: 60, w: 185, h: 720 },
      { k: 'txt', x: 905, y: 60, w: 185, h: 720 },
    ],
  },
  'text-well-three-column': {
    cover: false,
    blocks: [
      { k: 'txt', x: 45, y: 90, w: 155, h: 690 },
      { k: 'txt', x: 220, y: 90, w: 155, h: 690 },
      { k: 'txt', x: 395, y: 90, w: 155, h: 690 },
      { k: 'rule', x: 45, y: 55, w: 505, h: 4 },
      { k: 'txt', x: 640, y: 90, w: 155, h: 690 },
      { k: 'q', x: 815, y: 320, w: 240, h: 170 },
      { k: 'txt', x: 815, y: 90, w: 155, h: 200 },
      { k: 'txt', x: 990, y: 90, w: 155, h: 690 },
    ],
  },
  'sidebar-feature': {
    cover: false,
    blocks: [
      { k: 'txt', x: 60, y: 60, w: 220, h: 720 },
      { k: 'txt', x: 300, y: 60, w: 220, h: 720 },
      { k: 'txt', x: 650, y: 60, w: 160, h: 720 },
      { k: 'txt', x: 825, y: 60, w: 160, h: 720 },
      { k: 'box', x: 1005, y: 60, w: 145, h: 720 },
    ],
  },
  'quiet-single-column': {
    cover: false,
    blocks: [
      { k: 'txt', x: 200, y: 150, w: 195, h: 540 },
      { k: 'txt', x: 795, y: 150, w: 195, h: 540 },
    ],
  },
  'artwork-plate': {
    cover: false,
    blocks: [
      { k: 'img', x: 95, y: 110, w: 405, h: 500 },
      { k: 'cap', x: 95, y: 640, w: 300, h: 22 },
      { k: 'txt', x: 795, y: 190, w: 200, h: 420 },
    ],
  },
  'image-grid-quartet': {
    cover: false,
    blocks: [
      { k: 'hl', x: 75, y: 36, w: 280, h: 40 },
      { k: 'img', x: 75, y: 100, w: 495, h: 305 },
      { k: 'img', x: 620, y: 100, w: 495, h: 305 },
      { k: 'img', x: 75, y: 430, w: 495, h: 305 },
      { k: 'img', x: 620, y: 430, w: 495, h: 305 },
      { k: 'cap', x: 75, y: 760, w: 1040, h: 22 },
    ],
  },
  'image-interruption': {
    cover: false,
    blocks: [
      { k: 'img', x: 0, y: 0, w: S, h: 842 },
      { k: 'cap', x: 60, y: 780, w: 220, h: 22 },
    ],
  },
  'image-evidence-pair': {
    cover: false,
    blocks: [
      { k: 'img', x: 75, y: 80, w: 445, h: 440 },
      { k: 'cap', x: 75, y: 545, w: 340, h: 22 },
      { k: 'img', x: 670, y: 80, w: 445, h: 440 },
      { k: 'cap', x: 670, y: 545, w: 340, h: 22 },
      { k: 'txt', x: 670, y: 610, w: 310, h: 160 },
    ],
  },
  'stat-punch': {
    cover: false,
    blocks: [
      { k: 'hl', x: 60, y: 170, w: 480, h: 270 },
      { k: 'txt', x: 60, y: 490, w: 300, h: 60 },
      { k: 'cap', x: 60, y: 580, w: 200, h: 20 },
      { k: 'rule', x: 60, y: 630, w: 360, h: 4 },
      { k: 'txt', x: 650, y: 80, w: 240, h: 690 },
      { k: 'txt', x: 910, y: 80, w: 240, h: 690 },
    ],
  },
  'data-evidence': {
    cover: false,
    blocks: [
      { k: 'hl', x: 60, y: 70, w: 420, h: 60 },
      { k: 'tbl', x: 60, y: 170, w: 480, h: 430 },
      { k: 'cap', x: 60, y: 620, w: 260, h: 20 },
      { k: 'txt', x: 650, y: 80, w: 240, h: 690 },
      { k: 'txt', x: 910, y: 80, w: 240, h: 690 },
    ],
  },
  'document-evidence': {
    cover: false,
    blocks: [
      { k: 'hl', x: 75, y: 40, w: 320, h: 44 },
      { k: 'vrule', x: 590, y: 120, w: 4, h: 650 },
      { k: 'txt', x: 190, y: 140, w: 340, h: 95 },
      { k: 'txt', x: 660, y: 270, w: 340, h: 95 },
      { k: 'txt', x: 190, y: 400, w: 340, h: 95 },
      { k: 'txt', x: 660, y: 530, w: 340, h: 95 },
      { k: 'img', x: 1020, y: 270, w: 110, h: 95 },
    ],
  },
  'qa-alternating': {
    cover: false,
    blocks: [
      { k: 'txt', x: 60, y: 60, w: 230, h: 720 },
      { k: 'txt', x: 310, y: 60, w: 230, h: 720 },
      { k: 'txt', x: 650, y: 60, w: 230, h: 720 },
      { k: 'q', x: 900, y: 320, w: 240, h: 170 },
      { k: 'txt', x: 900, y: 60, w: 230, h: 220 },
      { k: 'txt', x: 900, y: 540, w: 230, h: 240 },
    ],
  },
  'qa-rapid-fire': {
    cover: false,
    blocks: [
      { k: 'txt', x: 120, y: 80, w: 340, h: 660 },
      { k: 'img', x: 620, y: 50, w: 520, h: 720 },
      { k: 'cap', x: 620, y: 790, w: 220, h: 20 },
    ],
  },
  'quote-beat': {
    cover: false,
    blocks: [
      { k: 'img', x: 0, y: 0, w: P, h: 842 },
      { k: 'q', x: 690, y: 300, w: 340, h: 210 },
      { k: 'rule', x: 690, y: 560, w: 220, h: 4 },
    ],
  },
  'fob-stack': {
    cover: false,
    blocks: [
      { k: 'hl', x: 60, y: 40, w: 180, h: 34 },
      { k: 'txt', x: 60, y: 110, w: 300, h: 150 },
      { k: 'img', x: 390, y: 110, w: 150, h: 130 },
      { k: 'rule', x: 60, y: 300, w: 480, h: 3 },
      { k: 'txt', x: 60, y: 340, w: 300, h: 150 },
      { k: 'img', x: 390, y: 340, w: 150, h: 130 },
      { k: 'rule', x: 60, y: 530, w: 480, h: 3 },
      { k: 'txt', x: 60, y: 570, w: 480, h: 150 },
      { k: 'hl', x: 650, y: 40, w: 180, h: 34 },
      { k: 'img', x: 650, y: 110, w: 150, h: 130 },
      { k: 'txt', x: 830, y: 110, w: 300, h: 150 },
      { k: 'rule', x: 650, y: 300, w: 480, h: 3 },
      { k: 'txt', x: 650, y: 340, w: 480, h: 150 },
      { k: 'rule', x: 650, y: 530, w: 480, h: 3 },
      { k: 'txt', x: 650, y: 570, w: 300, h: 150 },
      { k: 'img', x: 980, y: 570, w: 150, h: 130 },
    ],
  },
  'item-mosaic': {
    cover: false,
    blocks: [
      { k: 'img', x: 75, y: 80, w: 400, h: 320 },
      { k: 'txt', x: 75, y: 430, w: 400, h: 120 },
      { k: 'img', x: 510, y: 80, w: 220, h: 170 },
      { k: 'txt', x: 510, y: 270, w: 220, h: 110 },
      { k: 'box', x: 760, y: 80, w: 190, h: 300 },
      { k: 'img', x: 980, y: 80, w: 160, h: 140 },
      { k: 'txt', x: 980, y: 240, w: 160, h: 140 },
      { k: 'img', x: 510, y: 430, w: 300, h: 200 },
      { k: 'txt', x: 510, y: 650, w: 300, h: 110 },
      { k: 'txt', x: 850, y: 430, w: 290, h: 200 },
      { k: 'img', x: 850, y: 650, w: 290, h: 110 },
    ],
  },
  'how-to-object': {
    cover: false,
    blocks: [
      { k: 'img', x: 0, y: 0, w: P, h: 842 },
      { k: 'cap', x: 30, y: 786, w: 180, h: 20 },
      { k: 'hl', x: 650, y: 70, w: 340, h: 70 },
      { k: 'txt', x: 650, y: 180, w: 300, h: 430 },
      { k: 'box', x: 985, y: 180, w: 150, h: 320 },
      { k: 'cap', x: 650, y: 640, w: 210, h: 20 },
    ],
  },
  'closer-colophon': {
    cover: false,
    blocks: [
      { k: 'box', x: 0, y: 0, w: P, h: 842 },
      { k: 'img', x: 795, y: 280, w: 195, h: 170 },
      { k: 'txt', x: 795, y: 490, w: 195, h: 70 },
      { k: 'rule', x: 795, y: 600, w: 130, h: 3 },
    ],
  },
};

export function sketchFor(pattern: string): PatternSketch {
  return (
    PATTERN_SKETCHES[pattern] ?? {
      cover: false,
      blocks: [{ k: 'box', x: 60, y: 60, w: 1070, h: 720 }],
    }
  );
}
