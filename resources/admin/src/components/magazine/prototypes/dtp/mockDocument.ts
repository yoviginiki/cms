/**
 * M1 DTP Canvas Prototype — Mocked Document Model
 *
 * Static data for proving the canvas layout.
 * No database, no API, no persistence.
 */

export interface DtpFrame {
  id: string;
  type: 'text' | 'image' | 'quote' | 'pageNumber';
  pageIndex: number;   // 0 = left page, 1 = right page (in spread)
  x: number;           // px from page left
  y: number;           // px from page top
  width: number;
  height: number;
  rotation: number;
  zIndex: number;
  content?: string;    // text content or placeholder
  label?: string;      // human-readable name
}

export interface DtpPage {
  id: string;
  pageNumber: number;
  width: number;       // px
  height: number;      // px
  margins: { top: number; right: number; bottom: number; left: number };
  backgroundColor: string;
}

export interface DtpSpread {
  id: string;
  label: string;
  pages: DtpPage[];    // 1 (single) or 2 (spread)
  frames: DtpFrame[];
}

export interface DtpDocument {
  title: string;
  subtitle: string;
  pageSize: { width: number; height: number };
  spreads: DtpSpread[];
}

// ─── A4-ish page dimensions (595 × 842 pt ≈ pixels at 72dpi) ───
const PAGE_W = 595;
const PAGE_H = 842;
const MARGIN = { top: 48, right: 40, bottom: 56, left: 40 };

export const MOCK_DOCUMENT: DtpDocument = {
  title: 'Ensodo Magazine — Spring 2026',
  subtitle: 'Editorial Preview Issue',
  pageSize: { width: PAGE_W, height: PAGE_H },
  spreads: [
    // ─── Spread 1: Cover (single page) ───
    {
      id: 'spread-cover',
      label: 'Cover',
      pages: [
        { id: 'page-1', pageNumber: 1, width: PAGE_W, height: PAGE_H, margins: MARGIN, backgroundColor: '#ffffff' },
      ],
      frames: [
        {
          id: 'f-cover-title', type: 'text', pageIndex: 0,
          x: 40, y: 280, width: 515, height: 120, rotation: 0, zIndex: 2,
          content: 'ENSODO', label: 'Cover Title',
        },
        {
          id: 'f-cover-subtitle', type: 'text', pageIndex: 0,
          x: 40, y: 410, width: 515, height: 40, rotation: 0, zIndex: 1,
          content: 'Spring 2026 — Design & Technology', label: 'Cover Subtitle',
        },
        {
          id: 'f-cover-image', type: 'image', pageIndex: 0,
          x: 0, y: 0, width: PAGE_W, height: 260, rotation: 0, zIndex: 0,
          content: '', label: 'Cover Image',
        },
        {
          id: 'f-cover-pagenum', type: 'pageNumber', pageIndex: 0,
          x: 275, y: 800, width: 45, height: 24, rotation: 0, zIndex: 3,
          content: '1', label: 'Page Number',
        },
      ],
    },

    // ─── Spread 2: Editorial spread (two pages) ───
    {
      id: 'spread-editorial',
      label: 'Editorial Spread',
      pages: [
        { id: 'page-2', pageNumber: 2, width: PAGE_W, height: PAGE_H, margins: MARGIN, backgroundColor: '#ffffff' },
        { id: 'page-3', pageNumber: 3, width: PAGE_W, height: PAGE_H, margins: MARGIN, backgroundColor: '#ffffff' },
      ],
      frames: [
        // Left page (pageIndex 0)
        {
          id: 'f-ed-headline', type: 'text', pageIndex: 0,
          x: 40, y: 48, width: 515, height: 80, rotation: 0, zIndex: 2,
          content: 'The Future of Digital Publishing', label: 'Headline',
        },
        {
          id: 'f-ed-body', type: 'text', pageIndex: 0,
          x: 40, y: 148, width: 245, height: 580, rotation: 0, zIndex: 1,
          content: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.',
          label: 'Body Column 1',
        },
        {
          id: 'f-ed-body2', type: 'text', pageIndex: 0,
          x: 310, y: 148, width: 245, height: 580, rotation: 0, zIndex: 1,
          content: 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident.',
          label: 'Body Column 2',
        },
        {
          id: 'f-ed-pagenum-l', type: 'pageNumber', pageIndex: 0,
          x: 40, y: 800, width: 30, height: 24, rotation: 0, zIndex: 3,
          content: '2', label: 'Page Number',
        },

        // Right page (pageIndex 1)
        {
          id: 'f-ed-image', type: 'image', pageIndex: 1,
          x: 40, y: 48, width: 515, height: 380, rotation: 0, zIndex: 1,
          content: '', label: 'Feature Image',
        },
        {
          id: 'f-ed-quote', type: 'quote', pageIndex: 1,
          x: 60, y: 460, width: 475, height: 100, rotation: 0, zIndex: 2,
          content: '"Design is not just what it looks like — design is how it works."',
          label: 'Pull Quote',
        },
        {
          id: 'f-ed-caption', type: 'text', pageIndex: 1,
          x: 40, y: 580, width: 515, height: 160, rotation: 0, zIndex: 1,
          content: 'The intersection of editorial design and web technology is creating new possibilities for how we consume long-form content.',
          label: 'Caption Text',
        },
        {
          id: 'f-ed-pagenum-r', type: 'pageNumber', pageIndex: 1,
          x: 525, y: 800, width: 30, height: 24, rotation: 0, zIndex: 3,
          content: '3', label: 'Page Number',
        },
      ],
    },

    // ─── Spread 3: Gallery spread (two pages) ───
    {
      id: 'spread-gallery',
      label: 'Gallery Spread',
      pages: [
        { id: 'page-4', pageNumber: 4, width: PAGE_W, height: PAGE_H, margins: MARGIN, backgroundColor: '#f5f5f0' },
        { id: 'page-5', pageNumber: 5, width: PAGE_W, height: PAGE_H, margins: MARGIN, backgroundColor: '#f5f5f0' },
      ],
      frames: [
        {
          id: 'f-gal-img1', type: 'image', pageIndex: 0,
          x: 40, y: 48, width: 250, height: 360, rotation: 0, zIndex: 1,
          content: '', label: 'Gallery Image 1',
        },
        {
          id: 'f-gal-img2', type: 'image', pageIndex: 0,
          x: 305, y: 48, width: 250, height: 360, rotation: 0, zIndex: 1,
          content: '', label: 'Gallery Image 2',
        },
        {
          id: 'f-gal-text', type: 'text', pageIndex: 0,
          x: 40, y: 430, width: 515, height: 100, rotation: 0, zIndex: 1,
          content: 'A visual exploration of modern editorial layouts and their influence on digital publishing.',
          label: 'Gallery Text',
        },
        {
          id: 'f-gal-img3', type: 'image', pageIndex: 1,
          x: 40, y: 48, width: 515, height: 500, rotation: 0, zIndex: 1,
          content: '', label: 'Full Page Image',
        },
        {
          id: 'f-gal-quote', type: 'quote', pageIndex: 1,
          x: 60, y: 570, width: 475, height: 80, rotation: 0, zIndex: 2,
          content: '"Every great design begins with an even better story."',
          label: 'Pull Quote',
        },
        {
          id: 'f-gal-pn-l', type: 'pageNumber', pageIndex: 0,
          x: 40, y: 800, width: 30, height: 24, rotation: 0, zIndex: 3,
          content: '4', label: 'Page Number',
        },
        {
          id: 'f-gal-pn-r', type: 'pageNumber', pageIndex: 1,
          x: 525, y: 800, width: 30, height: 24, rotation: 0, zIndex: 3,
          content: '5', label: 'Page Number',
        },
      ],
    },
  ],
};
