# Slice 0.3: Section Primitive — Functional Structural Container

> **Goal**: Section block stores hierarchy, renders in Blade, has basic settings.
> **Time cap**: 5 working days
> **Change cap**: ~30 files

## Acceptance Criteria

1. ☐ Open page editor → see "Add Section" button (can be basic, polish comes in 0.8)
   Expected: Clickable button somewhere in the editor UI
   Actual: ___

2. ☐ Click "Add Section" → empty section appears in canvas
   Expected: Visible container element with label or border indicating it's a section
   Actual: ___

3. ☐ Click the section → settings panel opens on the right
   Expected: Panel with at minimum: padding-top, padding-bottom, background color
   Actual: ___

4. ☐ Set padding-top=100px, background=#e2e8f0 → section visually changes in canvas
   Expected: Section becomes taller (100px top padding) with light gray background
   Actual: ___

5. ☐ Save page → reload editor → section still has padding=100px and bg=#e2e8f0
   Expected: Values persist through save/load round-trip
   Actual: ___

6. ☐ Publish page → open published URL in new tab → inspect section element
   Expected: `padding-top: 100px` and `background-color: #e2e8f0` visible in computed styles
   Actual: ___

7. ☐ Open a different existing page → it still renders correctly with old blocks
   Expected: NO regression — existing flat blocks unaffected
   Actual: ___

## Pass condition: ALL 7 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
