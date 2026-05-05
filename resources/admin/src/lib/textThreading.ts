export interface ThreadFrame {
  id: string;
  threadId: string;
  threadOrder: number;
  width: number;
  height: number;
  content: string;
  columnsInFrame: number;
  columnGap: number;
  textInset: { top: number; right: number; bottom: number; left: number };
  typography: Record<string, unknown>;
}

export interface ThreadResult {
  frameContents: Map<string, string>; // frameId -> visible content
  overflowFrameId: string | null; // last frame with overflow, null if all fits
}

/**
 * Distribute full text content across a chain of threaded frames.
 * Uses a measuring approach: estimate characters that fit per frame based on dimensions.
 */
export function distributeThreadedText(frames: ThreadFrame[], fullContent: string): ThreadResult {
  const frameContents = new Map<string, string>();
  let remaining = fullContent;

  for (let i = 0; i < frames.length; i++) {
    const frame = frames[i];
    const inset = frame.textInset;
    const availW = (frame.width - inset.left - inset.right) / frame.columnsInFrame - (frame.columnsInFrame > 1 ? frame.columnGap : 0);
    const availH = frame.height - inset.top - inset.bottom;

    // Estimate: average 7px per character at 14pt, line height ~20px
    const fontSize = (frame.typography as any)?.fontSize || 14;
    const lineH = fontSize * ((frame.typography as any)?.lineHeight || 1.5);
    const charsPerLine = Math.floor(availW / (fontSize * 0.55));
    const lines = Math.floor(availH / lineH) * frame.columnsInFrame;
    const charsFit = Math.max(charsPerLine * lines, 20);

    if (remaining.length <= charsFit || i === frames.length - 1) {
      frameContents.set(frame.id, remaining);
      remaining = '';
      break;
    }

    // Split at word boundary near charsFit
    let splitAt = charsFit;
    while (splitAt > 0 && remaining[splitAt] !== ' ' && remaining[splitAt] !== '\n') splitAt--;
    if (splitAt === 0) splitAt = charsFit;

    frameContents.set(frame.id, remaining.slice(0, splitAt));
    remaining = remaining.slice(splitAt).trimStart();
  }

  // Fill remaining frames with empty
  for (const frame of frames) {
    if (!frameContents.has(frame.id)) {
      frameContents.set(frame.id, '');
    }
  }

  return {
    frameContents,
    overflowFrameId: remaining.length > 0 ? frames[frames.length - 1]?.id || null : null,
  };
}

export function getThreadChain(elements: Array<{ id: string; threadId: string | null; threadOrder: number | null }>, threadId: string): string[] {
  return elements
    .filter(e => e.threadId === threadId)
    .sort((a, b) => (a.threadOrder || 0) - (b.threadOrder || 0))
    .map(e => e.id);
}
