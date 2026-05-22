# MAG-DTP-AUDIT-1 — Properties Audit + Text Frame Fix

## 1. Route Audited
`/admin/sites/{site}/magazine-issues/{issue}/dtp-editor` → `DtpEditorBeta.tsx`

## 2. Root Cause — Rich Text Formatting Not Working
`document.execCommand` requires an active browser selection inside a focused contentEditable. When user clicks H1/Bold/Quote in the **side panel**, focus leaves the contentEditable → execCommand silently fails.

Previous fix used `requestAnimationFrame` but that's unreliable — the browser may not restore selection in one frame.

## 3. Fix
- contentEditable now saves selection (`Range`) to `window.__dtpSavedSelection` on every `keyUp` and `mouseUp`
- Toolbar `onFormatText` callback: focuses contentEditable → restores saved Range → runs `execCommand` → re-saves selection
- No `requestAnimationFrame` dependency — synchronous focus + selection restore

## 4. Properties Audit Matrix

| Area | Control | Status | Notes |
|------|---------|--------|-------|
| **Text Content** | | | |
| Inline editing | ✎ Edit Text button | WORKING | onStartEditing + contentEditable |
| Bold/Italic/Underline | RichTextToolbar | FIXED | Was failing due to lost focus |
| H1/H2/H3/P | RichTextToolbar | FIXED | formatBlock needs selection |
| Bullet/Numbered list | RichTextToolbar | FIXED | Same fix |
| Blockquote | RichTextToolbar | FIXED | Same fix |
| Alignment | RichTextToolbar | FIXED | Same fix |
| Clear formatting | RichTextToolbar | FIXED | Same fix |
| **Typography Panel** | | | |
| Font family | MagTypographyPanel | WORKING | Updates el.typography |
| Font size | MagTypographyPanel | WORKING | |
| Font weight | MagTypographyPanel | WORKING | |
| Line height | MagTypographyPanel | WORKING | |
| Letter spacing | MagTypographyPanel | WORKING | |
| Text color | MagTypographyPanel | WORKING | |
| Text align | MagTypographyPanel | WORKING | |
| Paragraph preset | MagTypographyPanel | WORKING | 5 presets |
| **Text Frame Panel** | | | |
| Overflow mode | TextFramePanel | WORKING | |
| Auto-size | TextFramePanel | WORKING | |
| Columns | TextFramePanel | WORKING | |
| Column gap/fill/rule | TextFramePanel | WORKING | |
| Text inset | TextFramePanel | FIXED | Was crashing on undefined |
| Vertical align | TextFramePanel | WORKING | |
| Text threading | TextFramePanel | WORKING | |
| **Transform** | | | |
| X/Y/W/H | TransformPanel | WORKING | |
| Rotation | TransformPanel | WORKING | |
| **Style** | | | |
| Fill/stroke | FillStrokePanel | WORKING | |
| Effects | EffectsPanel | WORKING | |
| Text wrap | TextWrapPanel | WORKING | |
| **Image** | | | |
| All controls | ImagePanel | WORKING | P15 verified |
| **Save/Load** | | | |
| Save DTP document | API PUT | WORKING | Spread ID fix applied |
| Load preserves | API GET → adapter | WORKING | |
| **Viewer** | | | |
| Preview renders | dtp-preview.blade | WORKING | |

## 5. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open DTP editor | Canvas loads |
| 2 | Click text frame | Properties panel shows |
| 3 | Click "✎ Edit Text" | Editing mode enters |
| 4 | Select text, click **H1** | Text becomes heading |
| 5 | Select text, click **Bold** | Text becomes bold |
| 6 | Click **Quote** | Text becomes blockquote |
| 7 | Click **Bullet list** | Text becomes list |
| 8 | Click "✓ Done" | Exits editing |
| 9 | Change font size in Typography panel | Canvas updates |
| 10 | Change font family | Canvas updates |
| 11 | Change text color | Canvas updates |
| 12 | Save document | No errors |
| 13 | Reload page | All formatting persists |
| 14 | Move/resize frame | Still works |
| 15 | Cross-page drag | Still works |
| 16 | Old editor fallback | Still works |

## 6. Known Limitations
- execCommand is deprecated but universally supported in all browsers
- formatBlock creates block-level elements — may conflict with frame typography
- Selection save uses window global `__dtpSavedSelection` (not ideal, but reliable)
