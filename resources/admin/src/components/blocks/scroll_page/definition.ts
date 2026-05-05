import type { BlockDefinition } from '@/types/blocks';

export const scrollPageDefinition: BlockDefinition = {
  type: 'scroll_page',
  category: 'layout',
  label: 'Scroll Page',
  icon: 'Layers',
  description: 'Immersive scroll experience with watercolor backdrop, mouse effects, and reveal animations.',
  defaultData: {
    typography: {
      fontDisplay: 'Fraunces', fontDisplayFallback: 'Georgia, serif',
      fontBody: 'EB Garamond', fontBodyFallback: 'Georgia, serif',
      googleFontsUrl: 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,500;1,9..144,300;1,9..144,400&family=EB+Garamond:ital,wght@0,400;0,500;1,400;1,500&display=swap',
      baseFontSize: '18px', bodyLineHeight: 1.7, maxReading: '36rem', maxWide: '52rem',
    },
    palette: {
      paper: '#EFE7D5', paperDeep: '#E6DCC6', ink: '#2A2117', inkSoft: '#4A3F32',
      rust: '#9B5A3E', ochre: '#C8A97E', terracotta: '#C4846A', sage: '#9DA58F', umber: '#7A5E47',
    },
    layout: { sectionPadding: '8rem 1.5rem', sectionMinHeight: '100vh', tallSectionMinHeight: '120vh', defaultTextAlign: 'center' },
    backdrop: {
      paperColor: '#EFE7D5',
      image: { enabled: false, assetId: null, url: null, baseBlur: '10px', baseSaturate: 0.88, overlayOpacity: 0.56, fit: 'cover', position: 'center' },
      svgBlobs: { enabled: true, blobs: [], blobOpacity: 0.22, blobBlendMode: 'multiply', filter: {}, filterSoft: {} },
      grain: { enabled: true, opacity: 0.25, blendMode: 'multiply', baseFrequency: 0.9, numOctaves: 2, seed: 5, colorMatrix: { r: 0.16, g: 0.13, b: 0.09, alpha: 0.35 } },
      vignette: { enabled: true, innerTransparent: '45%', outerColor: 'rgba(42,33,23,0.12)' },
    },
    mouseEffect: {
      enabled: true, preset: 'just-clouds', radius: '310px', intensity: 0.8, focus: 0.55,
      cursor: { shape: 'circle-dot', circleSize: '48px', circleStrokeWidth: '1px', circleColor: 'rust', circleOpacity: 0.65, dotSize: '6px', dotColor: 'rust', dotOpacity: 0.9, hideOsCursor: true, fadeInMs: 300, fadeOutMs: 300 },
      'just-clouds': { lightenAmount: 0.15, softness: '180px', pulseSeconds: 4.0, pulseAmplitude: 0.08 },
    },
    reveal: { enabled: true, duration: '2.4s', easing: 'cubic-bezier(0.16,1,0.3,1)', initialTranslateY: '30px', staggerMs: 250, observerThreshold: 0.15, observerRootMargin: '0px 0px -10% 0px' },
    responsive: { mobileBreakpoint: '640px', mobileMaxReading: '100%', mobileBaseFontSize: '16px', mobileSectionPadding: '5rem 1.25rem', mobileSectionMinHeight: '95vh', mobileBodyFontSize: '1.1rem', mobileDisableMouseEffect: true },
    scrollHint: { enabled: true, text: 'Scroll', fontSize: '0.8rem', letterSpacing: '0.25em', breatheDuration: '4s' },
    pages: [
      { id: crypto.randomUUID(), type: 'cover', tall: true, data: { eyebrow: 'A magazine for what does not shout', masthead: 'ENSŌDŌ', mastheadMeta: 'Issue No. 01', divider: '· · ·', subtitle: 'The Signal Within', hook: 'Come in quietly.', showScrollHint: true } },
      { id: crypto.randomUUID(), type: 'editorial_body', tall: false, data: { paragraphs: [{ text: 'Start writing your content here...', isLead: false, emphasis: [] }], centered: false, maxWidth: 'reading', showMark: false } },
      { id: crypto.randomUUID(), type: 'closing', tall: true, data: { line: 'Stay with what does not shout.', emphasis: [] } },
    ],
  },
  allowsChildren: false,
  tier: 'core',
};
