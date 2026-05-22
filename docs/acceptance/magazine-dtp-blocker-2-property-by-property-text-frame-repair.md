# MAG-DTP-BLOCKER-2 — Property-by-Property Text Frame Verification

## Active Files
- Route: `App.tsx:94` → `DtpEditorBeta.tsx`
- Typography panel: `MagTypographyPanel.tsx` → writes `el.typography`
- FillStroke panel: `FillStrokePanel.tsx` → writes `el.style.fill/stroke/cornerRadius`
- Effects panel: `EffectsPanel.tsx` → writes `el.style.opacity/shadow/blendMode`
- Canvas renderer: `MagElementRenderer.tsx` → reads `el.typography` + `el.style`
- Save: `pagesToDtpApi` → `metadata._typography`, `style: el.style`
- Load: `dtpFrameToElement` → `f.metadata._typography`, `f.style`

## Canonical Shape

### Typography (el.typography — MagTypography)
```
fontFamily, fontSize, fontWeight, fontStyle, lineHeight, letterSpacing,
textAlign, textColor, textTransform, textIndent, paragraphSpacingBefore,
paragraphSpacingAfter, hyphenation, hangingPunctuation, dropCap, openType,
orphans, widows, paragraphStyleId, characterStyleId
```

### Style (el.style — MagElementStyle)
```
fill: { color, opacity, gradient }
stroke: { color, width, style, alignment }
cornerRadius: { tl, tr, br, bl }
opacity: number
shadow: { x, y, blur, spread, color } | null
innerShadow: { x, y, blur, color } | null
blendMode: string
blur: number
```

## Property Verification

| Property | UI Panel | Canvas Reads | Save Key | Load Key | Status |
|----------|----------|-------------|----------|----------|--------|
| content.text | RichTextToolbar | el.data.content | content.html | content.html→data.content | ✅ CODE PASS |
| typography.fontFamily | MagTypographyPanel | el.typography.fontFamily | metadata._typography | savedTypography | ✅ CODE PASS |
| typography.fontSize | MagTypographyPanel | el.typography.fontSize | metadata._typography | savedTypography | ✅ CODE PASS |
| typography.fontWeight | MagTypographyPanel | el.typography.fontWeight | metadata._typography | savedTypography | ✅ CODE PASS |
| typography.textColor | MagTypographyPanel | el.typography.textColor | metadata._typography | savedTypography | ✅ CODE PASS |
| typography.textAlign | MagTypographyPanel | el.typography.textAlign | metadata._typography | savedTypography | ✅ CODE PASS |
| typography.lineHeight | MagTypographyPanel | el.typography.lineHeight | metadata._typography | savedTypography | ✅ CODE PASS |
| typography.letterSpacing | MagTypographyPanel | el.typography.letterSpacing | metadata._typography | savedTypography | ✅ CODE PASS |
| style.fill.color | FillStrokePanel | el.style.fill.color | style | f.style | ✅ CODE PASS |
| style.stroke.width | FillStrokePanel | el.style.stroke.width | style | f.style | ✅ CODE PASS |
| style.stroke.color | FillStrokePanel | el.style.stroke.color | style | f.style | ✅ CODE PASS |
| style.stroke.style | FillStrokePanel | el.style.stroke.style | style | f.style | ✅ CODE PASS |
| style.cornerRadius | FillStrokePanel | el.style.cornerRadius | style | f.style | ✅ CODE PASS |
| style.opacity | EffectsPanel | el.style.opacity | style | f.style | ✅ CODE PASS |
| style.shadow | EffectsPanel | el.style.shadow | style | f.style | ✅ CODE PASS |
| style.blendMode | EffectsPanel | el.style.blendMode | style | f.style | ✅ CODE PASS |
| columnsInFrame | TextFramePanel | data.columnsInFrame | content.columnsInFrame | data.columnsInFrame | ✅ CODE PASS |
| columnGap | TextFramePanel | data.columnGap | content.columnGap | data.columnGap | ✅ CODE PASS |
| textInset | TextFramePanel | data.textInset | content.textInset | data.textInset | ✅ CODE PASS |

## Browser Verification Required
All properties trace correctly through code. Niki must verify in browser:

1. Select text frame → change font size → canvas updates → save → reload → persists
2. Select text frame → change text color → canvas updates → save → reload → persists
3. Select text frame → change fill color (FillStroke panel) → canvas updates → save → reload
4. Select text frame → change stroke width/color → canvas shows border → save → reload
5. Select text frame → change corner radius → canvas shows rounded → save → reload
6. Select text frame → change shadow (Effects panel) → canvas shows shadow → save → reload
7. Select text frame → change opacity → canvas shows transparent → save → reload
8. Select text frame → set 2 columns → canvas shows 2 columns → save → reload → persists
