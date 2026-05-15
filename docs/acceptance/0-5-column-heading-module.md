# Slice 0.5: Column + First Module (Heading) End-to-End

> **Goal**: Column accepts modules. Heading works end-to-end. Full 4-level hierarchy functional.
> **Time cap**: 5 working days
> **Change cap**: ~40 files

## Acceptance Criteria

1. ☐ Open page with Section → Row (2-col) → see two empty Columns
   Expected: Each column shows a "+" button to add content
   Actual: ___

2. ☐ Click "+" in left column → module picker appears
   Expected: At minimum "Heading" option visible (other modules come later)
   Actual: ___

3. ☐ Pick "Heading" → heading appears inside the column with default text
   Expected: "Heading" text visible in the column area
   Actual: ___

4. ☐ Click the heading → settings panel shows: text input, level (h1-h6), font-size, color
   Expected: 4 controls, all editable
   Actual: ___

5. ☐ Change text to "Hello World", level to H1, size to 48px, color to #ff0000
   Expected: Heading updates live in canvas — large red "Hello World" as H1
   Actual: ___

6. ☐ Save → publish → open published page → inspect heading
   Expected: `<h1>` tag, font-size 48px, color #ff0000, text "Hello World"
   Actual: ___

7. ☐ Try to add Heading directly to Row (not inside Column) → blocked
   Expected: Action prevented — modules can only go in columns
   Actual: ___

8. ☐ Full round-trip: Page → Section → Row (2-col) → Column 1: Heading + Column 2: Heading → save → reload → both headings preserved with correct hierarchy
   Expected: Complete hierarchy survives save/load
   Actual: ___

## Pass condition: ALL 8 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Milestone Note
This is the first slice where the FULL 4-LEVEL HIERARCHY works end-to-end.
If this passes, the foundational architecture is proven.

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
