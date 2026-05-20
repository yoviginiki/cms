# MAG-P13 Shared Viewer / Editor Render Contract — Acceptance Checklist

## 1. Purpose

Define a canonical `MagazineDocument` TypeScript contract that all magazine systems share:
- DTP editor produces it
- DTP preview renders it
- Flipbook viewer can consume it (via adapter)
- Preflight validates against it
- Legacy magazines can be adapted to it

## 2. Current Document Formats

| System | Data Shape | Source | Pages? | Frames? |
|--------|-----------|--------|--------|---------|
| DTP Editor (P12) | MagPageData[] via magazineStore | DTP API → adapter | Yes | Yes (39 types) |
| DTP API | spreads/pages/frames JSON | magazine_spreads/dtp_pages/frames tables | Yes | Yes |
| DTP Preview | spreads→pages→frames rendered HTML | DtpRenderService | Yes | Yes |
| Flipbook Viewer | pages→elements JSON (% coords) | magazines/magazine_pages/elements tables | Yes | Yes (text/image/shape) |
| Old Editor | MagPageData[] (same as DTP) | magazines table + V2 markers | Yes | Yes (39 types) |
| Preflight | spreads/pages/frames from DB | DtpPreflightService | Yes | Yes |

## 3. Canonical MagazineDocument Contract

**File:** `resources/admin/src/types/magazineDocument.ts`

```typescript
MagazineDocument {
  version: 1
  title: string
  pageSize: { width, height }
  pages: MagazineDocPage[]
}

MagazineDocPage {
  id, index, name, width, height, margins, bleed, backgroundColor
  frames: MagazineDocFrame[]
}

MagazineDocFrame {
  id, type, name, x, y, width, height, rotation, zIndex, visible, locked
  content: text | image | shape | quote | pageNumber | line | decorative
}
```

Frame types: `text`, `image`, `shape`, `quote`, `pageNumber`, `line`, `decorative`

## 4. Normalization & Adapters

| Helper | Purpose |
|--------|---------|
| `createDefaultMagazineDocument()` | Safe empty document with one page |
| `normalizeMagazineDocument(raw)` | Fill missing fields, never crash |
| `isMagazineDocumentFrameBased(doc)` | Check if any page has frames |
| `dtpApiToMagazineDocument(apiData)` | DTP API response → MagazineDocument |
| `magazineDocumentToDtpApi(doc)` | MagazineDocument → DTP API save payload |
| `legacyMagazineToDocument(mag, pages)` | Legacy % coords → absolute pt coords |
| `magazineDocumentToViewerInput(doc)` | MagazineDocument → legacy viewer format (% coords) |

## 5. Viewer Integration Status

**PARTIAL.** The `magazineDocumentToViewerInput()` adapter converts MagazineDocument to the legacy viewer's expected format (percentage-based coordinates, pages→elements). The existing flipbook viewer (`magazine.blade.php`) can render this output. Full integration (wiring the adapter into the DTP preview route) is deferred to MAG-P15.

## 6. Editor Integration Status

**DONE.** The DTP editor (MAG-P12) already uses `MagPageData[]` via `magazineStore`. The `dtpApiToMagazineDocument()` and `magazineDocumentToDtpApi()` adapters bridge between the canonical contract and the DTP API. The editor's internal `MagElement` type maps to `MagazineDocFrame` via the type maps.

## 7. Preview / Render Health Status

**UNCHANGED.** `previewRenderable` from MAG-P8 still checks DtpRenderService resolvability + Blade view existence. The MagazineDocument contract doesn't change this — it adds a TypeScript-level contract, not a PHP render change. MAG-P15 may wire the viewer adapter into preview.

## 8. Preflight Integration Status

**UNCHANGED.** DtpPreflightService reads directly from DB (spreads/pages/frames). The MagazineDocument contract is a frontend type. Preflight could adopt it in the future but doesn't need to change now.

## 9. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Import `MagazineDocument` type | No TS errors |
| 2 | `createDefaultMagazineDocument()` | Returns doc with 1 page, 0 frames |
| 3 | `normalizeMagazineDocument(null)` | Returns default doc, no crash |
| 4 | `normalizeMagazineDocument({})` | Returns default doc |
| 5 | `dtpApiToMagazineDocument(apiData)` | Converts spreads/pages/frames correctly |
| 6 | `magazineDocumentToDtpApi(doc)` | Produces valid API payload |
| 7 | `legacyMagazineToDocument()` | Converts % coords to pt |
| 8 | `magazineDocumentToViewerInput()` | Converts pt to % coords |
| 9 | Round-trip: API → doc → API | No data loss for text/image frames |
| 10 | DTP editor still opens and works | Uses magazineStore as before |
| 11 | Old magazine editor still works | Not touched |
| 12 | Flipbook viewer still works | Not touched |
| 13 | Build passes | `npm run build --prefix resources/admin` |

## 10. Known Limitations

- Adapter is TypeScript-only — no PHP changes to viewer/render service
- `magazineDocumentToViewerInput()` is defined but not yet wired into any route
- Legacy adapter maps only text/image/shape — other types default to text
- No automatic migration of old magazines to MagazineDocument
- LinkedNextFrameId is in the contract but text threading not yet wired through it
- Typography in contract is a minimal subset (full typography stays in MagTypography)

## 11. Follow-ups

| Slice | Scope |
|-------|-------|
| MAG-P14 | Text frame inline editing + threading verification |
| MAG-P15 | Wire viewer adapter into DTP preview route |
| MAG-P16 | Preflight reads MagazineDocument if beneficial |
| MAG-P17 | Full viewer parity — flipbook renders DTP documents |
