/**
 * M7 DTP Canvas Prototype — Preflight Lite
 *
 * Lightweight layout/content validation for magazine spreads.
 * Detects missing images, empty frames, out-of-bounds geometry.
 * Prototype-only — no persistence, no export validation.
 */
import type { DtpFrame, DtpDocument, DtpPage } from './mockDocument';

export type IssueSeverity = 'error' | 'warning' | 'info';

export interface PreflightIssue {
  id: string;
  severity: IssueSeverity;
  code: string;
  message: string;
  frameId?: string;
  frameName?: string;
  pageNumber?: number;
  suggestion?: string;
}

export interface PreflightResult {
  status: 'pass' | 'warnings' | 'blocked';
  score: number;          // 0-100
  errors: PreflightIssue[];
  warnings: PreflightIssue[];
  info: PreflightIssue[];
  issues: PreflightIssue[];  // all combined
}

let issueCounter = 0;
function makeId(): string { return `pf-${++issueCounter}`; }

/**
 * Check if a frame is fully or partially outside a rectangle.
 */
function isOutside(frame: DtpFrame, width: number, height: number): 'full' | 'partial' | 'inside' {
  const r = frame.x + frame.width;
  const b = frame.y + frame.height;
  if (frame.x >= width || frame.y >= height || r <= 0 || b <= 0) return 'full';
  if (frame.x < 0 || frame.y < 0 || r > width || b > height) return 'partial';
  return 'inside';
}

/**
 * Check if frame violates margin/safe area.
 */
function violatesMargins(frame: DtpFrame, page: DtpPage): boolean {
  const m = page.margins;
  return (
    frame.x < m.left ||
    frame.y < m.top ||
    frame.x + frame.width > page.width - m.right ||
    frame.y + frame.height > page.height - m.bottom
  );
}

/**
 * Run preflight checks on the entire document.
 */
export function runPreflight(doc: DtpDocument): PreflightResult {
  issueCounter = 0;
  const issues: PreflightIssue[] = [];

  for (const spread of doc.spreads) {
    for (let pageIdx = 0; pageIdx < spread.pages.length; pageIdx++) {
      const page = spread.pages[pageIdx];
      const pageFrames = spread.frames.filter(f => f.pageIndex === pageIdx);

      for (const frame of pageFrames) {
        const name = frame.label || `${frame.type} frame`;

        // ─── Image checks ───
        if (frame.type === 'image') {
          if (!frame.image?.src) {
            issues.push({
              id: makeId(), severity: 'error', code: 'missing_image',
              message: `${name}: No image selected`,
              frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
              suggestion: 'Select an image in the Properties panel',
            });
          }
          if (frame.image?.src && !frame.image.alt) {
            issues.push({
              id: makeId(), severity: 'warning', code: 'missing_alt_text',
              message: `${name}: Missing alt text`,
              frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
              suggestion: 'Add descriptive alt text for accessibility',
            });
          }
        }

        // ─── Text checks ───
        if (frame.type === 'text' || frame.type === 'quote') {
          if (!frame.content || frame.content.trim() === '') {
            issues.push({
              id: makeId(), severity: 'warning', code: 'empty_text_frame',
              message: `${name}: Empty text frame`,
              frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
              suggestion: 'Add content or remove the empty frame',
            });
          }
        }

        // ─── Geometry checks ───
        if (frame.width <= 0 || frame.height <= 0) {
          issues.push({
            id: makeId(), severity: 'error', code: 'invalid_dimensions',
            message: `${name}: Invalid dimensions (${frame.width}x${frame.height})`,
            frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
            suggestion: 'Resize the frame to valid dimensions',
          });
        }

        const bounds = isOutside(frame, page.width, page.height);
        if (bounds === 'full') {
          issues.push({
            id: makeId(), severity: 'error', code: 'frame_outside_page',
            message: `${name}: Completely outside page`,
            frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
            suggestion: 'Move the frame back onto the page',
          });
        } else if (bounds === 'partial') {
          issues.push({
            id: makeId(), severity: 'warning', code: 'frame_outside_page',
            message: `${name}: Partially outside page bounds`,
            frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
            suggestion: 'Adjust position to keep within page bounds, or extend into bleed intentionally',
          });
        }

        if (bounds === 'inside' && violatesMargins(frame, page)) {
          issues.push({
            id: makeId(), severity: 'info', code: 'frame_outside_safe_area',
            message: `${name}: Extends beyond margins`,
            frameId: frame.id, frameName: name, pageNumber: page.pageNumber,
            suggestion: 'This may be intentional (bleed image) or accidental',
          });
        }
      }

      // ─── Page-level checks ───
      if (pageFrames.length === 0) {
        issues.push({
          id: makeId(), severity: 'info', code: 'empty_page',
          message: `Page ${page.pageNumber}: No frames`,
          pageNumber: page.pageNumber,
          suggestion: 'Add content frames to this page',
        });
      }
    }
  }

  // Calculate score and status
  const errors = issues.filter(i => i.severity === 'error');
  const warnings = issues.filter(i => i.severity === 'warning');
  const info = issues.filter(i => i.severity === 'info');

  let score = 100;
  score -= errors.length * 15;
  score -= warnings.length * 5;
  score -= info.length * 1;
  score = Math.max(0, Math.min(100, score));

  const status: PreflightResult['status'] =
    errors.length > 0 ? 'blocked' :
    warnings.length > 0 ? 'warnings' : 'pass';

  return { status, score, errors, warnings, info, issues };
}
