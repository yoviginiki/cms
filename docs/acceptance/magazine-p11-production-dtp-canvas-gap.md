# MAG-P11 Production DTP Canvas Gap — Diagnosis & Resolution

## Manual Acceptance Failure (Before Fix)

Niki opened the DTP beta editor and found:
- One page with one paragraph, no DTP controls
- No add-frame buttons, no text/image frame tools
- No zoom, snap, rulers, guides controls
- No spread management (add page/spread)
- No frame selection, move, resize, delete, duplicate
- The editor appeared to be a bare canvas with only save/load

## Root Cause

**Root cause #3: DTP API stored documents but the UI lacked frame creation tools.**

The `DtpEditorBeta.tsx` (MAG-P4) was implemented as a minimal bridge:
- It loaded/saved DTP documents via the API correctly
- It rendered the `SpreadCanvas` prototype component (which has full frame rendering with drag/resize/snap)
- But it was **missing frame creation tools** — no toolbar buttons to add text, image, quote, shape, or pageNumber frames
- Zoom was hardcoded to 50% with no controls
- Snap/guides/rulers were hardcoded on with no toggles
- No spread/page management (add spread button)
- No keyboard shortcuts for delete/duplicate/move

The prototype `DtpPrototypeShell.tsx` had all these features but used mock data. The production `DtpEditorBeta.tsx` used the same canvas components but skipped the tool/creation UI.

## Additional Bug Found

**Route model binding mismatch in 3 DTP controllers:**
- `DtpDocumentController` used `string $issueId` but route `{issue}` was globally bound to `MagazineIssue` via `Route::model()` in `AppServiceProvider`
- Same bug in `DtpPreviewController` and `DtpPreflightController`
- This caused 500 errors when loading the DTP document
- `DtpRolloutController` was already fixed in MAG-P7 hotfix but the other 3 were missed

## Resolution Applied (Same Session)

### Controller Fixes
All 3 controllers updated to accept `MagazineIssue $issue` with explicit site ownership check:
- `DtpDocumentController::show()` and `::save()`
- `DtpPreviewController::preview()`
- `DtpPreflightController::run()`

### Magazine Issue CRUD
- Created `MagazineIssueController` with list/create/delete endpoints
- Added routes: `GET/POST/DELETE /sites/{site}/magazine-issues`
- Added "New DTP issue" button to Magazine List (no longer depends on AI wizard)

### DTP Editor Toolbar
Added to `DtpEditorBeta.tsx`:
- Frame creation: Text (T), Image, Shape, Quote, Page Number
- Frame actions: Duplicate (Ctrl+D), Delete (Del/Backspace)
- Toggles: Rulers, Guides, Snap
- Zoom: In/Out/Fit controls
- Spread management: Add spread button (+) in left panel
- Keyboard shortcuts: Arrow keys (±1px, Shift ±10px), Delete, Ctrl+D

## Expected vs Actual Matrix (After Fix)

| Capability | Before Fix | After Fix | Prototype | API |
|-----------|-----------|-----------|-----------|-----|
| Page/spread canvas | ✓ (SpreadCanvas) | ✓ | ✓ | ✓ |
| Add spread | ✗ | ✓ (+button) | ✓ | ✓ |
| Add text frame | ✗ | ✓ (T button) | ✓ mock | ✓ |
| Add image frame | ✗ | ✓ (Image button) | ✓ mock | ✓ |
| Add quote/shape/pageNum | ✗ | ✓ | ✓ mock | ✓ |
| Select frame | ✓ (click) | ✓ | ✓ | — |
| Move/resize frame | ✓ (drag) | ✓ | ✓ | — |
| Delete frame | ✗ | ✓ (Del key + button) | ✗ | — |
| Duplicate frame | ✗ | ✓ (Ctrl+D + button) | ✗ | — |
| Zoom controls | ✗ (fixed 50%) | ✓ (+/−/fit) | ✓ | — |
| Rulers/guides/snap | ✗ (fixed on) | ✓ (toggles) | ✓ | — |
| Properties panel | ✓ | ✓ | ✓ | — |
| Layers panel | ✓ | ✓ | ✓ | — |
| Preflight panel | ✓ | ✓ | ✓ | ✓ |
| Preview button | ✓ | ✓ | — | ✓ |
| Status panel | ✓ | ✓ | — | ✓ |
| Save/load | ✓ | ✓ | ✗ mock | ✓ |
| Keyboard shortcuts | ✗ | ✓ | ✓ | — |

## Remaining Gaps

| Gap | Priority | Slice |
|-----|----------|-------|
| Edit text frame inline (contentEditable) | HIGH | MAG-P12 |
| Image upload/picker for image frames | HIGH | MAG-P12 |
| Undo/redo | MEDIUM | MAG-P13 |
| Align/distribute multi-select | LOW | MAG-P13 |
| Template gallery connected to API | LOW | MAG-P14 |
| Master page assignment | LOW | MAG-P14 |
| Auto-save | MEDIUM | MAG-P13 |

## Files Changed

- `app/Http/Controllers/Api/V1/DtpDocumentController.php` — route model binding fix
- `app/Http/Controllers/Api/V1/DtpPreviewController.php` — route model binding fix
- `app/Http/Controllers/Api/V1/DtpPreflightController.php` — route model binding fix
- `app/Http/Controllers/Api/V1/MagazineIssueController.php` — new CRUD controller
- `routes/api.php` — issue CRUD routes + existing DTP routes
- `resources/admin/src/pages/DtpEditorBeta.tsx` — frame tools, zoom, snap, keyboard
- `resources/admin/src/pages/MagazineList.tsx` — New DTP Issue button, issue section
- `resources/admin/src/lib/api.ts` — rollout/preflight API client methods

## Manual Acceptance Status

**MAG-P10 acceptance: PARTIAL** — the 106-check protocol was created but the editor was not functional during initial browser testing. After the fixes in this session:
- API endpoints work (500 bug fixed)
- Frame tools available
- Canvas renders with frames
- Save/load works
- Browser testing should be re-run
