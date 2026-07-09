// Shared editor-chrome colors + z-indices for the canvas overlay layer, so the
// guide / selection / handle / peer colors stay consistent and can't drift
// between CanvasSection and CanvasElement.
export const CHROME = {
  guide: '#ec4899',                     // smart-guide lines
  selection: '#2563eb',                 // selection outline, resize + rotate handles
  peerLock: '#f59e0b',                  // element another editor is actively moving
  mobileOutline: 'rgba(37,99,235,0.35)', // mobile-breakpoint canvas outline
  idleOutline: 'rgba(37,99,235,0.25)',   // unselected element dashed outline
} as const;

export const CHROME_Z = {
  peerCursor: 10000,                    // peer cursors float above all editor chrome
} as const;
