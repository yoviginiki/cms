# Slice 0.4: Row Primitive — Column Layout System

> **Goal**: Row block inside Section, predefined column layouts, renders in Blade.
> **Time cap**: 5 working days
> **Change cap**: ~30 files

## Acceptance Criteria

1. ☐ Open page with a Section → see "Add Row" button inside the section
   Expected: Button visible within the section area
   Actual: ___

2. ☐ Click "Add Row" → layout picker appears with at least 6 options
   Expected: Visual grid showing: 1-col, 2-col (1/2+1/2), 2-col (1/3+2/3), 3-col (1/3×3), 4-col (1/4×4), 2-col (1/4+3/4)
   Actual: ___

3. ☐ Pick "2 column 1/2 + 1/2" → Row appears with 2 empty column slots
   Expected: Two equal-width empty areas visible inside the row
   Actual: ___

4. ☐ Click Row → settings panel → set max-width=1000px, gap=32px
   Expected: Row narrows to 1000px, columns spread 32px apart
   Actual: ___

5. ☐ Save → publish → inspect published page
   Expected: Row element has `max-width: 1000px` and column gap of `32px`
   Actual: ___

6. ☐ Try to drag/add Row directly to page (not inside Section) → blocked
   Expected: Error message or prevented action — Row cannot exist outside Section
   Actual: ___

7. ☐ Existing pages still work
   Expected: NO regression
   Actual: ___

## Pass condition: ALL 7 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
