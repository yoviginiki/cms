# Hero Block — Regression Baseline Acceptance Checklist

> **Purpose**: Manual verification of current Hero state before any refactor.
> **Status**: Pending Niki's manual test
> **Date created**: 2026-05-15

This checklist defines what Hero can do TODAY. Every future slice must preserve all PASS items.

---

## Instructions

1. Open the page editor in browser
2. Add a new Hero block (or use an existing one)
3. Test each criterion below
4. Mark PASS or FAIL
5. If FAIL, note what's broken — that's a pre-existing bug, not a regression

---

## Content (3 tests)

1. ☐ Type title text in side panel → title appears in canvas preview
   Expected: Text renders immediately in preview
   Actual: ___

2. ☐ Type subtitle text in side panel → subtitle appears below title
   Expected: Subtitle renders in preview
   Actual: ___

3. ☐ Click title in canvas → edit inline → text updates in side panel
   Expected: Inline editing works bidirectionally
   Actual: ___

## Layout (4 tests)

4. ☐ Change Heading Tag to H3 → inspect preview → tag is `<h3>`
   Expected: Correct HTML tag renders
   Actual: ___

5. ☐ Set Section Height to "Large (600px)" → preview becomes taller
   Expected: Visible height change in canvas
   Actual: ___

6. ☐ Set Vertical Position to "Bottom" → content moves to bottom of hero
   Expected: Content visually at bottom
   Actual: ___

7. ☐ Set Content Max Width to "500px" → content area becomes narrower
   Expected: Text block narrows, centered
   Actual: ___

## Background (3 tests)

8. ☐ Set background type to Color → pick #3b82f6 → hero turns blue
   Expected: Blue background visible in canvas
   Actual: ___

9. ☐ Set background type to Image → upload/select image → hero shows image
   Expected: Image fills hero background
   Actual: ___

10. ☐ Set overlay color to black, opacity 50% → image darkens
    Expected: Semi-transparent overlay visible over image
    Actual: ___

## Typography (3 tests)

11. ☐ Set Title Size to "4rem" → title becomes large
    Expected: Visible size change in canvas
    Actual: ___

12. ☐ Set Title Color to #ff0000 → title turns red
    Expected: Red title text in canvas
    Actual: ___

13. ☐ Set Subtitle Weight to "Bold (700)" → subtitle becomes bold
    Expected: Visible weight change
    Actual: ___

## CTA Button (2 tests)

14. ☐ Type button text "Learn More" + URL "https://example.com" → button appears
    Expected: Visible button with text in canvas
    Actual: ___

15. ☐ Change CTA Variant to "Outline" → button style changes to outline
    Expected: Transparent bg with border visible
    Actual: ___

## Section Border & Shadow (2 tests)

16. ☐ Set Section Border Width "2px", Color "#333", Style "Solid" → border appears
    Expected: Visible border around hero section
    Actual: ___

17. ☐ Set Section Shadow to "Medium" preset → shadow appears below hero
    Expected: Visible drop shadow
    Actual: ___

## Shared Panel Controls (3 tests)

18. ☐ Open Layout panel → set Max Width "800px" → hero wrapper narrows
    Expected: Entire hero block constrained to 800px
    Actual: ___

19. ☐ Open Spacing panel → set Padding Top "60px" → hero gets taller with top padding
    Expected: Extra space at top of wrapper
    Actual: ___

20. ☐ Save → reload page → ALL above settings preserved
    Expected: All values persist after save+reload, no loss
    Actual: ___

---

## Pass Condition

**ALL 20 criteria must PASS.** If any FAIL, note it as pre-existing issue.

## Regression Rule

Before and after every future slice, re-run items 4, 8, 11, 14, 18, 19, 20 (quick regression subset).

---

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | First baseline run |
