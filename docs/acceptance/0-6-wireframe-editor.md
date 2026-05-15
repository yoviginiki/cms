# Slice 0.6: Wireframe Editor Mode

> **Goal**: Structural outline view of the page — labeled boxes showing hierarchy.
> **Time cap**: 2 weeks (larger slice — dual week allowed per v3 plan)
> **Change cap**: ~60 files

## Acceptance Criteria

1. ☐ Open page editor → see toggle/button to switch to "Wireframe" mode
   Expected: Clearly labeled toggle — "Wireframe" vs "Visual" (or icons)
   Actual: ___

2. ☐ Click Wireframe → canvas switches to structural outline view
   Expected: No rendered content — only labeled boxes like:
   ```
   ┌─ SECTION ──────────────────┐
   │ ┌─ ROW (2 cols) ─────────┐ │
   │ │ ┌─ COL 1/2 ┐ ┌─ COL 1/2 ┐│
   │ │ │ Heading  │ │ (empty)  ││
   │ │ └──────────┘ └──────────┘│
   │ └─────────────────────────┘ │
   └────────────────────────────┘
   ```
   Actual: ___

3. ☐ Click a block in wireframe → it becomes selected → settings panel opens on right
   Expected: Same settings panel as visual mode, same data
   Actual: ___

4. ☐ Edit heading text in settings panel (while in wireframe) → label updates in wireframe
   Expected: Wireframe box shows updated text/label
   Actual: ___

5. ☐ Wireframe loads fast even with 10+ sections
   Expected: No noticeable lag — wireframe is lightweight boxes, not full renders
   Actual: ___

6. ☐ Switch back to Visual mode → same content visible, rendered live
   Expected: Seamless switch, no data loss, no reload
   Actual: ___

7. ☐ Selection persists across mode switch — select heading in Wireframe → switch to Visual → same heading still selected
   Expected: Selected block ID preserved across mode toggle
   Actual: ___

## Pass condition: ALL 7 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Architecture Validation Note
**This is the "moment of truth" slice.** If dual-mode rendering works cleanly
with single Zustand store and two renderers, the entire v3 architecture is validated.
If it doesn't work, we reconsider before investing in Track A-H.

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
