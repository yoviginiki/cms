# MP1 — Magazine DTP Data Model Audit & Migration Plan

## 1. Summary

**Finding: No new migrations required for beta.** The existing `mag_pages`, `mag_elements`, and `mag_styles` tables already have every column the DTP prototype needs. The beta DTP designer can read/write the same tables as the current MagEditor. New tables are only needed for future phases (MP5+): templates, master page definitions, asset references, preflight persistence.

---

## 2. Current Production Tables Inventory

### 2.1 mag_pages (EXISTS — 18 columns)

| Column | Type | Default | Prototype Field |
|--------|------|---------|-----------------|
| id | uuid PK | | DtpPage.id |
| page_id | uuid FK→pages | | (parent CMS page) |
| page_number | integer | | DtpPage.pageNumber |
| page_size | jsonb | {width:595,height:842} | DtpPage.width/height |
| margins | jsonb | {top:36,right:36,...} | DtpPage.margins |
| bleed | jsonb | {top:9,...} | DtpPage.bleed (not in prototype) |
| columns | jsonb | {count:1,gutter:12} | (used by guides) |
| baseline_grid | jsonb | {start:36,increment:14} | (used by guides) |
| master_page_id | uuid nullable | | DtpPage.masterPageId |
| is_master | boolean | false | (master page flag) |
| spread_with | integer nullable | | DtpSpread pairing |
| background_color | varchar(20) | | DtpPage.backgroundColor |
| background_asset_id | uuid nullable | | (background image) |
| spread_role | text nullable | | (wizard metadata) |
| spread_density | text nullable | | (wizard metadata) |
| spread_tension | text nullable | | (wizard metadata) |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** PK, (page_id, is_master), UNIQUE(page_id, page_number)
**FK:** page_id → pages(id) CASCADE
**RLS:** Via page → site → tenant chain
**Status: COMPLETE — no columns missing for beta**

### 2.2 mag_elements (EXISTS — 27 columns)

| Column | Type | Default | Prototype Field |
|--------|------|---------|-----------------|
| id | uuid PK | | DtpFrame.id |
| page_id | uuid FK→pages | | (parent CMS page) |
| parent_id | uuid nullable | | (for groups) |
| type | varchar(40) | | DtpFrame.type |
| name | varchar(255) nullable | | DtpFrame.label |
| data | jsonb | {} | DtpFrame.content + image |
| x | double | 0 | DtpFrame.x |
| y | double | 0 | DtpFrame.y |
| width | double | 200 | DtpFrame.width |
| height | double | 100 | DtpFrame.height |
| rotation | double | 0 | DtpFrame.rotation |
| scale_x | double | 1 | (not in prototype) |
| scale_y | double | 1 | (not in prototype) |
| z_index | integer | 0 | DtpFrame.zIndex |
| locked | boolean | false | DtpFrame.locked |
| visible | boolean | true | DtpFrame.visible |
| layer_name | varchar(255) nullable | | (layer grouping) |
| style | jsonb | {} | (fill, stroke, effects) |
| typography | jsonb nullable | | (font, size, color...) |
| text_wrap | jsonb | {type:none,...} | (text wrap settings) |
| thread_id | uuid nullable | | (text threading) |
| thread_order | integer nullable | | (thread position) |
| page_number | integer | 1 | (which page in doc) |
| on_master | boolean | false | DtpFrame.isMasterObject |
| responsive_overrides | jsonb | {} | (responsive) |
| created_by | uuid nullable FK→users | | |
| created_at/updated_at | timestamp | | |

**Indexes:** PK, (page_id, page_number, z_index), (page_id, thread_id, thread_order), (page_id, parent_id), (page_id, on_master)
**FK:** page_id → pages(id) CASCADE, created_by → users(id) SET NULL
**RLS:** Via page → site → tenant chain
**Status: COMPLETE — all 15 prototype fields map to existing columns**

### 2.3 mag_styles (EXISTS — 11 columns)

| Column | Type | Purpose |
|--------|------|---------|
| id | uuid PK | |
| site_id | uuid FK→sites | |
| name | varchar(255) | Style name |
| type | varchar(20) | paragraph/character/object/table/cell |
| properties | jsonb | Style definition |
| based_on | uuid nullable | Inheritance chain |
| next_style | uuid nullable | InDesign-style "next" |
| sort_order | integer | Ordering |
| is_default | boolean | Default flag |
| created_at/updated_at | timestamp | |

**Status: COMPLETE — supports paragraph/character style inheritance**

### 2.4 magazine_issues (EXISTS — 18 columns)

Manages issues with title, theme, status, AI wizard data, linked CMS page.
**Status: COMPLETE for beta**

### 2.5 Legacy tables (magazines, magazine_pages, magazine_elements)

Used by legacy MagazineEditorV2. **Keep untouched — old editor reads/writes these.**

---

## 3. Prototype → Production Field Mapping

### DtpFrame → mag_elements (ALL FIELDS EXIST)

| Prototype Field | DB Column | Notes |
|-----------------|-----------|-------|
| id | id | UUID |
| type | type | text/image/quote/pageNumber/shape/line |
| x, y | x, y | double precision |
| width, height | width, height | double precision |
| rotation | rotation | double precision |
| zIndex | z_index | integer |
| visible | visible | boolean, default true |
| locked | locked | boolean, default false |
| content | data.content | Inside JSONB |
| label | name | varchar(255) |
| image.src | data.src | Inside JSONB |
| image.alt | data.alt | Inside JSONB |
| image.fitMode | data.fit | Inside JSONB |
| image.focalPoint | data.focalPoint | Inside JSONB |
| image.opacity | data.opacity | Inside JSONB |
| isMasterObject | on_master | boolean |
| masterPageId | (via page's master_page_id) | Inferred |

**Gap: NONE.** Every prototype field maps to an existing column.

---

## 4. Tables Needed for Future Phases

### 4.1 magazine_templates (MP8 — future)
```
id             uuid PK
site_id        uuid FK→sites
name           varchar(255)
description    text nullable
target         varchar(10)    -- 'page' | 'spread'
thumbnail_url  varchar(500) nullable
frames         jsonb          -- template frame definitions
sort_order     integer DEFAULT 0
is_system      boolean DEFAULT false
created_at/updated_at timestamp
```
**When needed:** MP8 (template persistence)
**Risk:** LOW — additive table, no existing data affected

### 4.2 magazine_master_pages (MP8 — future)
```
id             uuid PK
site_id        uuid FK→sites
name           varchar(255)
applies_to     varchar(10)    -- 'left' | 'right' | 'both'
frames         jsonb          -- master frame definitions
sort_order     integer DEFAULT 0
created_at/updated_at timestamp
```
**When needed:** MP8 (master page persistence)
**Risk:** LOW — additive table

### 4.3 magazine_preflight_runs (MP7 — future)
```
id             uuid PK
page_id        uuid FK→pages
status         varchar(20)    -- 'pass' | 'warnings' | 'blocked'
score          integer
issues         jsonb          -- array of issue objects
run_at         timestamp
```
**When needed:** MP7 (preflight persistence)
**Risk:** LOW — additive table

### 4.4 magazine_asset_refs (MP5 — future)
```
id             uuid PK
element_id     uuid FK→mag_elements
asset_id       uuid FK→assets
role           varchar(20)    -- 'content' | 'background' | 'mask'
created_at     timestamp
```
**When needed:** MP5 (asset tracking for preflight)
**Risk:** LOW — additive table

---

## 5. Beta Schema Requirements (MP3-MP4)

### Required for first beta: ZERO new migrations

The existing schema is sufficient:
- `mag_pages` stores page geometry, margins, bleed, master page reference
- `mag_elements` stores frame geometry, content, style, typography, threading, visibility, locking
- `mag_styles` stores paragraph/character styles

### API endpoints already exist:
- `GET /sites/{site}/pages/{page}/magazine` — load document
- `PUT /sites/{site}/pages/{page}/magazine` — save document (atomic replace)
- `POST /sites/{site}/pages/{page}/magazine/pages` — add page
- `DELETE /sites/{site}/pages/{page}/magazine/pages/{n}` — delete page
- `GET/POST/PUT/DELETE /sites/{site}/magazine-styles` — CRUD styles

**Beta can launch without any database changes.**

---

## 6. Validation & Security Plan

### Text frames (data.content):
- Sanitize with DOMPurify on client (already implemented)
- Server-side: HTMLPurifier or strip_tags with allowlist (recommended for MP3)
- Allowlist: p, br, b, i, em, strong, span, a, h1-h6, ul, ol, li, blockquote

### Image URLs (data.src):
- Client: validate http/https only (already implemented)
- Server: validate URL scheme, reject javascript:/data: (recommended for MP3)

### Style JSON (style column):
- Validate structure on server (CSS property allowlist)
- Reject values containing `{`, `}`, `<`, `>` in CSS values
- Max JSON size: 64KB per element

### Typography JSON:
- Validate fontFamily against known safe patterns
- Validate numeric ranges (fontSize 1-999, fontWeight 100-900)

### Frame geometry:
- Validate x/y as finite numbers
- Validate width/height > 0
- Validate rotation 0-360
- Validate z_index integer range

### General:
- All JSONB columns: max 256KB per field
- Rate limiting on sync endpoint (max 1 save per 3 seconds)
- RLS policies already enforce tenant isolation

---

## 7. Testing Plan

### Migration tests (when migrations created in MP5+):
- Forward migration creates tables
- Rollback drops tables cleanly
- No data loss in existing tables

### CRUD tests (MP3):
- Create page → save → reload → verify all fields preserved
- Create element → save → reload → verify geometry + content
- Update element → save → verify changes persisted
- Delete element → save → verify removed

### Validation tests (MP3):
- Reject XSS in text content (script tags, event handlers)
- Reject unsafe image URLs (javascript:, data:)
- Reject invalid geometry (NaN, Infinity, negative dimensions)
- Reject oversized JSON (>256KB)

### Round-trip tests (MP4):
- Load document in DTP designer
- Edit frame geometry
- Save
- Reload in DTP designer → verify identical state
- Load in old MagEditor → verify no corruption (if same tables)

### Regression tests:
- Old magazine editor still opens and functions
- Old flipbook viewer still renders
- AI wizard still provisions to canvas

---

## 8. Backward Compatibility

### Old editor (MagazineEditorV2):
- Reads/writes `magazines`, `magazine_pages`, `magazine_elements` (legacy tables)
- **Completely unaffected** by DTP designer
- No shared tables between legacy and DTP paths

### MagEditor (current DTP-compatible editor):
- Reads/writes `mag_pages`, `mag_elements` via MagEditorController
- **Same tables** as DTP designer beta
- Both can read/write interchangeably
- No data format conflict (same JSONB structure)

### Conversion:
- Legacy → DTP: `convertLegacyElement()` already exists in MagazineEditorV2
- DTP → Legacy: `_v2` round-trip markers already preserve data
- **No forced migration** — users can continue using whichever editor they prefer

---

## 9. Recommended Next Step

**MP2: Create shared TypeScript types and normalizers.**

Create production-grade TypeScript types that map to the `mag_elements` schema, replacing the prototype's `DtpFrame` with a type that matches the real database columns. No UI changes, no API changes — just shared types that both editors can use.

This is a zero-risk code task that prepares for MP3 (save/load integration).
