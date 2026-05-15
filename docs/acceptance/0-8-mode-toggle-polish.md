# Slice 0.8: Mode Toggle + Polish

> **Goal**: Smooth mode switching, keyboard shortcuts, selection persistence, UX polish.
> **Time cap**: 5 working days
> **Change cap**: ~30 files

## Acceptance Criteria

1. ☐ Mode toggle is visually clear — user always knows which mode they're in
   Expected: Prominent indicator (icon + label, or segmented control) showing current mode
   Actual: ___

2. ☐ Keyboard shortcut switches modes (e.g., Ctrl+Shift+W for Wireframe, Ctrl+Shift+V for Visual)
   Expected: Instant switch, no lag
   Actual: ___

3. ☐ "Add Section" UX polished — clear insert points at top/bottom of page
   Expected: "+" button or "Add Section" appears at clear locations
   Actual: ___

4. ☐ "Add Row" UX polished — insert point inside each section
   Expected: Clear visual affordance for adding rows within sections
   Actual: ___

5. ☐ "Add Module" UX polished — "+" in each column with module picker
   Expected: Module picker modal/dropdown with icons and labels
   Actual: ___

6. ☐ Drag-and-drop reordering works for sections (move section up/down)
   Expected: Sections can be reordered by dragging in both modes
   Actual: ___

7. ☐ Drag-and-drop respects containment — cannot drag module out of column into section
   Expected: Invalid drop targets are not highlighted, drop is prevented
   Actual: ___

8. ☐ Delete block works — select block, press Delete or click trash icon → block removed
   Expected: Block and its children removed, undo possible via Ctrl+Z
   Actual: ___

9. ☐ Full end-to-end scenario: Create page → Add Section → Add Row (3-col) → Add Heading in col 1 → Add Heading in col 2 → Edit both → Switch to Wireframe → Reorder → Switch to Visual → Save → Publish → View published → All correct
   Expected: Complete workflow without errors
   Actual: ___

## Pass condition: ALL 9 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Track 0 Complete Note
If this slice passes, Track 0 is DONE. The foundational architecture
(hierarchy + dual mode + primitives) is proven and ready for Track A-H.

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
