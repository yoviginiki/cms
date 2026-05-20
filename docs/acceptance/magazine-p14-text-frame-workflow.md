# MAG-P14 Text Frame Workflow — Acceptance Checklist

## 1. Purpose
Polish the production DTP canvas text frame workflow with curated fonts, paragraph style presets, and improved typography controls.

## 2. Text Frame Model (Already Existing)
The text frame model from MAG-P12/P13 already supports:
- `content.text` / `content.html` — text content
- `typography` — full MagTypography with 30+ fields
- `threadId` / `threadOrder` — linked text frames
- `overflow` — visible/hidden/threaded
- `columnsInFrame` / `columnGap` / `columnFill` / `columnRule` — multi-column
- `textInset` — per-side insets
- `verticalAlign` — top/center/bottom
- `autoSize` — none/grow-height/shrink-text

## 3. Inline Editing (Already Existing from MAG-P12)
- Double-click text frame → contentEditable mode
- Escape exits editing
- Blur exits editing
- Keyboard shortcuts suppressed while editing
- Content sanitized (strips event handlers/scripts)
- Undo captures text edits via magazineStore snapshots
- Save/load preserves text via DTP API adapter

## 4. Typography Controls (Improved in P14)

### Font Family Select
Replaced free-text input with curated dropdown:
- Inter, Roboto, Open Sans, Montserrat, Lato, Poppins
- Merriweather, Playfair Display, Source Sans 3, Barlow
- Barlow Condensed, Manrope, Nunito Sans, Raleway, Oswald
- Georgia, Times New Roman, Arial, Helvetica
- Custom fonts preserved if already set

### Already Existing Controls
- Font size (6–200)
- Font weight (100–900)
- Italic toggle
- Line height (0.5–4)
- Letter spacing (em)
- Text align (left/center/right/justify)
- Text transform (none/uppercase/lowercase/capitalize/small-caps)
- Text color (picker + input)
- Text indent
- Paragraph spacing (before/after)
- Drop cap (enable, lines, font, color)
- OpenType (ligatures, oldstyle nums, small caps)
- Orphans & widows
- Max chars per line
- Hyphenation
- Hanging punctuation

## 5. Paragraph Style Presets (New in P14)

| Preset | Font | Size | Weight | Line Height | Color |
|--------|------|------|--------|-------------|-------|
| Headline | Playfair Display | 48 | 700 | 1.1 | #1a1a1a |
| Subheading | Inter | 24 | 600 | 1.3 | #333333 |
| Body | Inter | 14 | 400 | 1.6 | #1a1a1a |
| Caption | Inter | 11 | 400 (italic) | 1.4 | #666666 |
| Quote | Merriweather | 20 | 400 (italic) | 1.5 | #333333 |

- Presets appear in "Style preset" dropdown at top of Typography panel
- Also available in "Paragraph style" dropdown at bottom
- Applying preset updates typography fields
- User can override any field after applying
- `paragraphStyleId` saved with frame

## 6. Overflow Detection (Already Existing)
- MagElementRenderer detects overflow via DOM scrollHeight vs clientHeight
- Red overflow indicator shown on frame when text overflows
- Updates on text edit, font change, resize
- Preflight can detect overflow via frame data

## 7. Linked Text Frames (Already Existing from P12)
- TextFramePanel has "Start new thread" and "Continue thread" buttons
- Thread ID and position shown when linked
- "Remove from thread" action available
- Thread data persisted via `threadId` / `threadOrder` in save adapter
- Actual automatic text splitting is not implemented (documented)

## 8. Text Balance / Fill
**DEFERRED** — Automatic text distribution across linked frames is future work. The current system stores links but does not auto-split text content. Documented as MAG-P15.

## 9. Viewer Compatibility
- Viewer renders text frames with typography applied via CSS
- Font family fallback used (no font loading API)
- linkedNextFrameId does not affect viewer rendering (frames rendered independently)

## 10. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open DTP editor | Canvas with pages/frames loads |
| 2 | Add text frame | Frame appears on canvas |
| 3 | Double-click text frame | Inline editing mode (blue outline, cursor) |
| 4 | Type text | Text appears in frame |
| 5 | Press Escape | Exits editing mode |
| 6 | Click outside frame | Exits editing mode |
| 7 | Open Typography panel | Font family dropdown with curated list |
| 8 | Change font to Playfair Display | Text updates visually |
| 9 | Change font size to 48 | Text gets larger |
| 10 | Apply "Headline" preset | Typography updates to preset values |
| 11 | Override font size after preset | Override persists |
| 12 | paragraphStyleId shows "Headline" | Indicator under preset dropdown |
| 13 | Change alignment to Center | Text centers in frame |
| 14 | Change color | Text color updates |
| 15 | Resize frame smaller | Overflow indicator appears |
| 16 | Resize frame larger | Overflow indicator clears |
| 17 | Save document | No errors |
| 18 | Reload page | Text and typography persist |
| 19 | Start text thread | Thread ID appears in TextFrame panel |
| 20 | Continue thread on another frame | Second frame shows thread info |
| 21 | Old editor still works | Navigate to /magazines/:id/edit |
| 22 | Preflight still works | Status panel shows preflight result |

## 11. Known Limitations
- Font loading from Google Fonts not implemented (CSS fallback only)
- Font family dropdown is curated list, not all system fonts
- Automatic text flow across linked frames not implemented
- Text balance/fill deferred to MAG-P15
- No rich text toolbar (bold/italic/underline inline) — typography applies to entire frame
- Character styles have no preset options yet
- Global style manager not implemented (local presets only)
