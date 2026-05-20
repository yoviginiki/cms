# Magazine DTP API Contract

> Fills gaps between MP0/MP1/MP2 docs and MAG-P3 implementation.
> Does NOT duplicate existing docs — see [MAGAZINE-PRODUCTION-INTEGRATION-PLAN.md](MAGAZINE-PRODUCTION-INTEGRATION-PLAN.md) for roadmap, [MAGAZINE-DTP-DATA-MODEL-PLAN.md](MAGAZINE-DTP-DATA-MODEL-PLAN.md) for schema audit.

## 1. Existing Foundation

| Artifact | Status |
|----------|--------|
| MP0 Integration Plan | Merged — roadmap, migration strategy, risks |
| MP1 Data Model Plan | Merged — schema audit, field mapping |
| MP2 Migrations + Models | Merged (code, no separate doc) — 5 tables, RLS, Eloquent, FrameType enum, rules. See `database/migrations/2026_05_19_000001_create_dtp_designer_tables.php` and `app/Domain/Magazine/` |
| Feature flag | `config('features.magazine_dtp_designer_enabled')` default false |
| Prototype M1-M9 | Merged — full DTP canvas with all features |

---

## 2. DTP Editor Document Schema

### GET response shape
```json
{
  "issue": {
    "id": "uuid",
    "title": "Spring 2026",
    "slug": "spring-2026",
    "status": "draft",
    "updated_at": "2026-05-19T12:00:00Z"
  },
  "spreads": [
    {
      "id": "uuid",
      "spread_index": 0,
      "name": "Cover",
      "page_ids": ["uuid"],
      "metadata": {}
    }
  ],
  "pages": [
    {
      "id": "uuid",
      "spread_id": "uuid",
      "page_index": 0,
      "side": "single",
      "width": 595,
      "height": 842,
      "bleed": { "top": 9, "right": 9, "bottom": 9, "left": 9 },
      "margins": { "top": 36, "right": 36, "bottom": 36, "left": 36 },
      "safe_area": null,
      "background": { "color": "#ffffff", "asset_id": null },
      "master_page_id": null,
      "metadata": {}
    }
  ],
  "layers": [
    {
      "id": "uuid",
      "page_id": "uuid",
      "name": "Content",
      "layer_order": 0,
      "visible": true,
      "locked": false
    }
  ],
  "frames": [
    {
      "id": "uuid",
      "page_id": "uuid",
      "layer_id": "uuid",
      "spread_id": null,
      "frame_type": "text",
      "name": "Headline",
      "x": 40,
      "y": 48,
      "width": 515,
      "height": 80,
      "rotation": 0,
      "z_index": 2,
      "visible": true,
      "locked": false,
      "content": {},
      "style": {},
      "metadata": {}
    }
  ],
  "asset_references": [
    {
      "id": "uuid",
      "frame_id": "uuid",
      "source_url": "https://...",
      "alt": "Description",
      "caption": "Photo credit"
    }
  ],
  "meta": {
    "content_hash": "sha256...",
    "frame_count": 16,
    "page_count": 5,
    "spread_count": 3
  }
}
```

---

## 3. Frame Content Schemas by Type

### text
```json
{
  "content": {
    "html": "<p>Body text</p>",
    "overflow": "hidden",
    "columns": 1,
    "columnGap": 12,
    "columnFill": "auto",
    "textInset": { "top": 8, "right": 8, "bottom": 8, "left": 8 },
    "verticalAlign": "top"
  },
  "style": {
    "fill": { "color": null, "opacity": 1 },
    "stroke": { "color": "transparent", "width": 0 },
    "cornerRadius": { "tl": 0, "tr": 0, "br": 0, "bl": 0 }
  },
  "metadata": {
    "threadId": null,
    "threadOrder": null,
    "paragraphStyleId": null
  }
}
```

### image
```json
{
  "content": {
    "src": "https://...",
    "alt": "Description",
    "caption": "Credit",
    "fitMode": "fill",
    "focalPoint": { "x": 50, "y": 50 },
    "opacity": 100
  }
}
```

### shape
```json
{
  "content": {
    "shapeType": "rectangle",
    "fillColor": "#333333",
    "strokeColor": "#000000",
    "strokeWidth": 1,
    "cornerRadius": 0
  }
}
```

### quote
```json
{
  "content": {
    "html": "<p><em>\"Quote text\"</em></p>",
    "attribution": "— Author"
  }
}
```

### pageNumber
```json
{
  "content": {
    "format": "numeric",
    "prefix": "",
    "suffix": ""
  },
  "metadata": { "onMaster": true }
}
```

### line
```json
{
  "content": {
    "x1": 0,
    "y1": 0,
    "x2": 200,
    "y2": 0,
    "strokeColor": "#000000",
    "strokeWidth": 1,
    "strokeStyle": "solid",
    "capStart": "none",
    "capEnd": "none"
  }
}
```

### articleReference
```json
{
  "content": {
    "articleId": "uuid",
    "field": "body",
    "truncate": 500
  }
}
```

### decorative
```json
{
  "content": {
    "decorType": "rule",
    "pattern": "solid"
  }
}
```

---

## 4. API Endpoints

### Load document
```
GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-document
Authorization: Sanctum + tenant scope
Feature flag: magazine_dtp_designer_enabled required
Response: 200 + full document JSON (section 2)
```

### Save document (atomic)
```
PUT /api/v1/sites/{site}/magazine-issues/{issue}/dtp-document
Body: { spreads: [], pages: [], layers: [], frames: [], asset_references: [] }
Validation: MagazineFrameRules + geometry + URL safety
Response: 200 + saved document JSON
Conflict: 409 if content_hash mismatch (optional, MAG-P4)
```

### Create frame
```
POST /api/v1/sites/{site}/magazine-issues/{issue}/dtp-frames
Body: { page_id, frame_type, x, y, width, height, content, style }
Response: 201 + created frame
```

### Update frame
```
PATCH /api/v1/sites/{site}/magazine-issues/{issue}/dtp-frames/{frame}
Body: { x, y, width, height } or { content } or { visible, locked }
Response: 200 + updated frame
```

### Delete frame
```
DELETE /api/v1/sites/{site}/magazine-issues/{issue}/dtp-frames/{frame}
Response: 204
```

### Duplicate frame
```
POST /api/v1/sites/{site}/magazine-issues/{issue}/dtp-frames/{frame}/duplicate
Response: 201 + new frame (offset +10px)
```

### Reorder frames
```
POST /api/v1/sites/{site}/magazine-issues/{issue}/dtp-frames/reorder
Body: { frame_ids: ["uuid", "uuid", ...] }
Response: 200 + { reordered: true }
```

### Run preflight
```
POST /api/v1/sites/{site}/magazine-issues/{issue}/dtp-preflight
Response: 200 + { status, score, issues: [] }
```

---

## 5. Request/Response Examples

### Save geometry change
```
PATCH /api/v1/sites/{site}/magazine-issues/{issue}/dtp-frames/{frame}
Content-Type: application/json

{ "x": 120, "y": 48, "width": 400, "height": 300 }

→ 200 OK
{ "data": { "id": "uuid", "x": 120, "y": 48, "width": 400, "height": 300, ... } }
```

### Save text content
```
PATCH .../dtp-frames/{frame}
{ "content": { "html": "<p>Updated text</p>" } }
→ 200 OK
```

### Validation error
```
PATCH .../dtp-frames/{frame}
{ "width": -50, "frame_type": "invalid" }

→ 422 Unprocessable Entity
{
  "message": "Validation failed.",
  "errors": {
    "width": ["Width must be at least 1."],
    "frame_type": ["The selected frame type is invalid."]
  }
}
```

### Feature flag off
```
GET .../dtp-document
→ 404 Not Found
{ "message": "DTP Designer is not enabled for this site." }
```

---

## 6. Validation Rules

| Field | Rules |
|-------|-------|
| frame_type | required, in: text,image,shape,line,quote,pageNumber,articleReference,decorative |
| x, y | required, numeric |
| width, height | required, numeric, min:1 |
| rotation | numeric, min:0, max:360 |
| z_index | integer, min:-100, max:9999 |
| visible, locked | boolean |
| content | nullable, array, max:256KB |
| style | nullable, array, max:256KB |
| metadata | nullable, array, max:256KB |
| content.html | sanitized (HTMLPurifier), max:100KB |
| content.src | nullable, url, starts_with:http |
| page_id | required, uuid, exists:magazine_dtp_pages |
| layer_id | nullable, uuid, exists:magazine_layers |
| issue ownership | middleware: issue.site_id === route site |

---

## 7. Service/Controller Mapping

| Component | Purpose |
|-----------|---------|
| `DtpDocumentController` | GET/PUT full document |
| `DtpFrameController` | CRUD + duplicate + reorder frames |
| `DtpPreflightController` | Run preflight checks |
| `DtpDocumentService` | Load/save document assembly |
| `DtpFrameService` | Frame CRUD with validation |
| `DtpPreflightService` | Preflight rules engine |
| `DtpDocumentResource` | API response transformation |
| `SaveDtpDocumentRequest` | Form request with validation |

---

## 8. MAG-P3 — First Implementation Slice

### Scope
- Add routes behind feature flag middleware
- Create `DtpDocumentController` with GET + PUT
- Create `DtpDocumentService` for load/save
- Create `DtpDocumentResource` for response
- Create `SaveDtpDocumentRequest` with validation
- Tests: load empty, load existing, save valid, reject invalid, feature flag off

### Acceptance
- GET returns issue + spreads + pages + layers + frames
- PUT persists valid document atomically
- Invalid geometry → 422
- Unsafe image URL → 422
- Feature flag off → 404
- Old magazine editor unaffected
- 41 existing magazine tests still pass

### Not in MAG-P3
- No frontend connection
- No autosave
- No PATCH frame
- No duplicate/reorder
- No preflight endpoint
- No publish/export

---

## 9. Testing Checklist

| Test | Phase |
|------|-------|
| GET empty issue → empty document | MAG-P3 |
| GET existing issue → full document | MAG-P3 |
| PUT valid document → persisted | MAG-P3 |
| PUT invalid frame_type → 422 | MAG-P3 |
| PUT negative width → 422 | MAG-P3 |
| PUT javascript: URL → 422 | MAG-P3 |
| PUT XSS in text HTML → sanitized | MAG-P3 |
| Feature flag off → 404 | MAG-P3 |
| Issue ownership → 403/404 | MAG-P3 |
| Old editor regression | MAG-P3 |
| PATCH frame geometry | MAG-P4 |
| Reorder z-index | MAG-P4 |
| Layer visibility/lock | MAG-P4 |
| Preflight from real data | MAG-P6 |

---

## 10. Rollout Status Endpoint (MAG-P7)

### Endpoint
```
GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-rollout
Authorization: Sanctum + tenant scope
Feature flag: NOT required — always available (reports status even when flag off)
```

### Response
```json
{
  "data": {
    "status": "legacy | dtp_beta | dtp_ready",
    "canOpenDtp": true,
    "canPromote": false,
    "hasDtpData": true,
    "dtpStats": { "spreads": 3, "pages": 5, "frames": 16 },
    "preflight": { "status": "warning", "score": 85, "counts": {} },
    "blockingReasons": [],
    "warnings": [],
    "links": {
      "legacyEditor": "/admin/sites/.../magazines/.../edit",
      "dtpEditor": "/admin/sites/.../magazine-issues/.../dtp-editor (SPA route)",
      "dtpPreview": "/api/v1/sites/.../magazine-issues/.../dtp-preview",
      "preflight": "/api/v1/sites/.../magazine-issues/.../dtp-preflight"
    },
    "capabilities": {
      "dtpFeatureEnabled": true,
      "hasDtpDocument": true,
      "hasSpreadOrPage": true,
      "previewLinkAvailable": true,
      "previewRenderable": true,
      "legacyFallbackAvailable": true,
      "productionStatePersisted": false
    }
  }
}
```

### Rollout States
| State | Condition | Notes |
|-------|-----------|-------|
| legacy | Flag off or no DTP document | Default state |
| dtp_beta | DTP document exists, preflight has blocking errors | Beta testing |
| dtp_ready | DTP document exists, preflight passes | Ready for promotion |
| dtp_production | Persisted editor_mode = production | **Reserved** |

### Link Types
- `links.dtpEditor` is a React SPA route (not a Laravel route)
- `links.legacyEditor` is always present regardless of feature flag

### Capabilities
- `previewLinkAvailable` — true when feature flag on and DTP document exists; indicates the preview link can be shown to the user
- `previewRenderable` — true when the full render pipeline is available: feature flag on, DTP document exists, `DtpRenderService` resolvable, and `dtp-preview` Blade view exists. Fails closed (false if any component missing). Does not simply mirror `previewLinkAvailable`.
- `productionStatePersisted` — false until `editor_mode` column is added

---

## 11. Rollout Note

- Old editor remains available at existing routes
- DTP designer route requires feature flag
- No production replacement until MAG-P8+ acceptance
- Rollback: disable feature flag → designer hidden instantly
- DTP document requires at least one spread or page (frames alone are not sufficient)
