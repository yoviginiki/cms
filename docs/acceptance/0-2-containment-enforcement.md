# Slice 0.2: Containment Enforcement

> **Goal**: Server API rejects invalid hierarchies. Client-side validation hooks exist.
> **Time cap**: 5 working days
> **Change cap**: ~15 files

## Acceptance Criteria

1. ☐ POST `/api/v1/sites/{id}/pages/{id}/blocks` with module directly inside section → API returns 422
   Expected: Clear error message like "Module cannot be direct child of Section. Place it inside a Column."
   Actual: ___

2. ☐ POST same endpoint with valid hierarchy (Section → Row → Column → Module) → API returns 200
   Expected: Blocks saved successfully
   Actual: ___

3. ☐ POST with row directly at page root (no parent section) → API returns 422
   Expected: Error "Row must be inside a Section"
   Actual: ___

4. ☐ Open browser console → call `canMoveBlock(tree, headingId, sectionId)` → returns `{ valid: false, reason: "..." }`
   Expected: Invalid move detected with human-readable reason
   Actual: ___

5. ☐ Call `canMoveBlock(tree, headingId, columnId)` → returns `{ valid: true }`
   Expected: Valid move accepted
   Actual: ___

6. ☐ Existing pages with flat blocks still save and load without errors
   Expected: NO regression — old format handled by adapter
   Actual: ___

7. ☐ `git diff --stat HEAD` → NO UI component files modified
   Expected: Only controllers, validators, services, lib, tests
   Actual: ___

## Pass condition: ALL 7 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
