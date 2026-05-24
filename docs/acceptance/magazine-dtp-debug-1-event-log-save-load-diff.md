# MAG-DTP-DEBUG-1 — DTP Debug Mode, Event Log, Save/Load Diff Inspector

## Summary

Developer debug panel for the DTP editor that provides real-time event logging,
save/load round-trip diff inspection, and live store state viewing.

Feature-flagged: only visible when `localStorage.getItem('dtp-debug') === '1'`.
Debug logging is gated — no performance cost when debug mode is off.

## Files Changed

- `resources/admin/src/stores/magazineStore.ts` — Debug event log state + instrumented actions
- `resources/admin/src/components/magazine/DtpDebugPanel.tsx` — Debug panel component (lazy-loaded)
- `resources/admin/src/pages/DtpEditorBeta.tsx` — Debug mode wiring, save/load payload capture

## Manual Acceptance Checklist

### Debug Toggle
- [ ] Default state: debug panel NOT visible
- [ ] `localStorage.setItem('dtp-debug', '1')` + reload shows Debug button in status bar
- [ ] Clicking Debug button opens/closes the panel
- [ ] `localStorage.removeItem('dtp-debug')` + reload hides Debug button

### Event Log Tab
- [ ] Shows timestamped events as you interact
- [ ] `frame:add` event when adding an element (shows type, position, id)
- [ ] `frame:update` event when changing properties (shows old/new values, changed paths)
- [ ] `frame:update` with severity=error if element not found on current page
- [ ] `frame:delete` event when deleting elements
- [ ] `undo` / `redo` events
- [ ] `page:add` / `page:delete` events
- [ ] `settings:change` event when changing layout mode
- [ ] `save:start` / `save:payload` / `save:success` lifecycle events
- [ ] `save:fail` event with error message on save failure
- [ ] `load:start` / `load:success` events on document load
- [ ] Each event shows: timestamp, source, action, severity, selected element type/id
- [ ] Error events highlighted with red background
- [ ] Clear button empties the log
- [ ] Copy button copies log JSON to clipboard
- [ ] Export button downloads log as JSON file

### Diff Inspector Tab
- [ ] Shows "Save to generate diff" message before first save
- [ ] After save: shows tree diff of load payload vs save payload
- [ ] Added fields shown in green
- [ ] Removed fields shown in red with strikethrough
- [ ] Changed fields shown in yellow with old → new values
- [ ] Important fields (typography, content.src, layoutMode, etc.) marked with `!`
- [ ] Lost field count shown in header bar
- [ ] Important lost fields trigger orange warning banner
- [ ] Copy/Export buttons available for diff data

### Store State Tab
- [ ] Shows live collapsible JSON tree of store state
- [ ] Includes: pageCount, selectedIds, isDirty, zoom, viewMode, issueSettings
- [ ] Per-page: id, pageNumber, elementCount, elements with type/position
- [ ] Copy button copies state snapshot to clipboard

### Panel UX
- [ ] Resizable via drag handle (min 150px, max 50vh)
- [ ] Dark theme, monospace font, 10-11px text
- [ ] Error/warning counts shown in header when > 0

### Viewer Isolation
- [ ] Public viewer (tenant domains) does NOT show debug UI
- [ ] `dtp-preview.blade.php` has no debug references

### Security
- [ ] No auth tokens, cookies, or passwords in debug output
- [ ] Debug default off — must be explicitly enabled
- [ ] Export is user-initiated only (click required)
