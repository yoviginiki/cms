# Magazine Wizard

AI-guided magazine planning that walks the user through 7 editorial
steps with a conversational art director. Produces a structured magazine
issue ready for the Magazine Editor canvas.

## Flow

```
Step 1 (Brief) ──> Step 2 (Structure) ──> Step 3 (Select) ──> Step 4 (Analyze)
                                                                      │
Step 7 (Review) <── Step 6 (Thumbnails) <── Step 5 (Directions) <─────┘
       │
       └──> Provision ──> Magazine Issue + Pages in Editor
```

Each step has:
- A **chat panel** (conversational AI with streaming)
- An **artifact panel** (structured deliverable, editable by user)
- A **lock** action that commits the artifact and advances

Users can **unlock** to go back, which clears later steps but
preserves all messages.

## Data Model

```
mag_wizard_sessions          # One per wizard run
├── tenant_id                # RLS isolation
├── user_id                  # Owner
├── current_step (1-7)       # Progress
├── status (active|provisioned|abandoned)
├── step1_brief (jsonb)      # Locked artifacts
├── step2_structure (jsonb)
├── step3_article_selection (jsonb)
├── step4_analyses (jsonb[])  # Array: one per article
├── step5_directions (jsonb[])
├── step6_thumbnails (jsonb[])
└── provisioned_issue_id     # Set on provision

mag_wizard_messages          # Chat history
├── session_id (FK)
├── step (1-7)
├── role (user|assistant)
├── content (text)
├── artifact_update (jsonb)  # Parsed from AI response
├── tokens_in / tokens_out
└── created_at

On provision:
magazine_issues              # Created from wizard
├── wizard_brief (jsonb)     # Step 1 data

mag_articles                 # One per article in structure
├── issue_id (FK)
├── slug, title, page_count, rhythm
├── wizard_plan (jsonb)      # Combined analysis + direction + thumbnails
└── sort_order

mag_pages                    # One per page per article
├── page_id (FK to pages)
├── spread_role              # From step 4 spread assignments
├── spread_density
└── spread_tension
```

## Artifact Schemas

### Step 1 — Brief
```json
{ "feeling": "string", "reader_state": "string", "anchors": ["string"], "page_count": 24 }
```

### Step 2 — Structure
```json
{ "articles": [{ "slug": "string", "title": "string", "pages": 2, "rhythm": "dense|medium|breath", "role": "string", "justification": "string" }] }
```

### Step 3 — Selection
```json
{ "selected_slug": "string" }
```

### Step 4 — Analysis
```json
{ "article_slug": "string", "voice": { "tone": "string", "register": "string", "posture": "string" }, "beats": [{ "name": "string", "description": "string" }], "spread_assignments": [{ "spread": 1, "beat": "string", "role": "string", "density": "string", "tension": "string" }] }
```

### Step 5 — Directions
```json
{ "article_slug": "string", "proposed": [{ "name": "string", "thesis": "string", ... }], "chosen": { ... } }
```

### Step 6 — Thumbnails
```json
{ "article_slug": "string", "spreads": [{ "spread": 1, "weight_position": "string", "zones": [{ "kind": "text|image", "rough": "string" }], "entry_exit": "string", "flagged_for_revision": false }] }
```

### Step 7 — Review
```json
{ "review_complete": true, "notes": "string" }
```

## How to Extend

### Adding a new step
1. Add `stepN_newstep` jsonb column to `mag_wizard_sessions` (migration)
2. Add to `WizardSession` model's `$fillable` and `casts`
3. Create `storage/app/wizard/step_N.txt` with instructions + artifact schema
4. Update `WizardController::stepColumn()` mapping
5. Update `WizardPromptBuilder::buildLockedDecisions()` stepMap
6. Create `StepNArtifact.tsx` component
7. Add case to `ArtifactPanel.tsx`
8. Update `LockBar.tsx` validity check
9. Update `STEP_LABELS` and `STEP_COUNT` in `types.ts`

### Changing artifact schemas
1. Update the schema in `storage/app/wizard/step_N.txt`
2. Update the TypeScript type in `types.ts`
3. Update the artifact component to match
4. Update `WizardProvisioner` if the schema affects provisioning

## Operations

### Monitoring
- All `sendMessage`, `lockStep`, `unlockStep`, and `provision` calls
  are logged to the default Laravel log channel
- Key fields: `session_id`, `step`, `tokens_in`, `tokens_out`, `duration_ms`
- Provision failures log to `error` level with stack traces

### Common failure modes
| Symptom | Cause | Fix |
|---------|-------|-----|
| SSE stream hangs | Anthropic API timeout | Retry; check API key |
| 422 on provision | Missing brief/structure | Unlock and complete steps |
| 409 on lock | Step mismatch | Refresh session state |
| Empty artifact panel | AI emitted `<artifact_update>null</artifact_update>` | Continue conversation |

### Recovery
- Sessions are never hard-deleted — abandoned sessions preserve all data
- Stale sessions (>14 days inactive) are auto-abandoned by daily scheduler
- Provisioned issues can be edited in Magazine Editor independently

## PostgreSQL Dependencies
<!-- PHASE_12_PORT -->
- jsonb columns: `step1_brief` through `step6_thumbnails`, `wizard_plan`, `wizard_brief`
- RLS policies on: `mag_wizard_sessions`, `mag_wizard_messages`, `mag_articles`
- `gen_random_uuid()` for primary keys
- `current_setting('app.current_tenant_id', true)::uuid` in RLS policies
