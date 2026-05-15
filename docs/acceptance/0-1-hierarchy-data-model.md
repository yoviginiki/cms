# Slice 0.1: Hierarchy Data Model + DB Schema

> **Goal**: Define 4-level page composition hierarchy in DB and TS/PHP types. NO UI work.
> **Time cap**: 5 working days
> **Change cap**: ~20 files

## Acceptance Criteria

After Claude Code completes this slice, Niki manually:

1. ☐ Open migration file in IDE → see `level` column (string, values: section/row/column/module) added to blocks table
   Expected: Column definition with DEFAULT 'module' (safe for existing data)
   Actual: ___

2. ☐ Run `php artisan migrate` on dev DB → succeeds
   Expected: Migration applies without error, existing blocks get level='module'
   Actual: ___

3. ☐ Run `php artisan migrate:rollback` → succeeds
   Expected: Column removed cleanly, no data loss
   Actual: ___

4. ☐ Open `resources/admin/src/types/block-hierarchy.ts` → see HierarchicalBlock type with `level` field
   Expected: `level: BlockLevel` where BlockLevel = `'section' | 'row' | 'column' | 'module'`
   Actual: ___

5. ☐ Run `php artisan test --filter=HierarchyValidator` → ALL pass
   Expected: Valid hierarchy passes, invalid cases (module in section, row in row, column at root) all caught
   Actual: ___

6. ☐ Run `git diff --stat HEAD` → ONLY migrations, types, validators, tests, fixtures, Block model
   Expected: ZERO UI component files modified. Block.php modified only to add level/preset_id to fillable.
   Actual: ___

## Pass condition: ALL 6 criteria PASS
## Fail condition: ANY criterion FAIL → slice not done

## Results

| Date | Tested by | Result | Notes |
|------|-----------|--------|-------|
| _pending_ | Niki | _pending_ | |
