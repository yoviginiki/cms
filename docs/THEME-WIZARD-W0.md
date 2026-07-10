# Theme Wizard (T3) — W0 Pre-flight & Reuse Surface

**Date:** 2026-07-10 · **Branch:** `feature/theme-wizard` · **Status:** W0 recon complete, W1 NOT started (phase gate)

The Theme Wizard: a conversational, AI-assisted theme creator. Two entry paths — **from reference** ("here's a site I like → make me a theme with that feel, slightly different": screenshot/URL → Opus vision → design-token profile → candidate `theme.json`) and **from conversation** (mood/industry interview → token profile). Output is a **standard T1-contract theme** (never a parallel format), previewed through the live-preview loop we already built (the `studioFrame` showcase), refined by conversational nudges, and on accept it becomes a real theme editable in Theme Studio.

## Pre-flight
- **DB targets:** production `cms_saas_platform`; tests `cms_saas_platform_test` (phpunit.xml). Confirmed.
- **Models available:** `claude-sonnet-5` (routing/interview), `claude-opus-4-8` (generation + vision). API key from shared `config('cms.ai.api_key')`.
- **Server-side reference capture:** Playwright + chromium are installed → W2's "capture a reference URL server-side → screenshot → Opus vision" is feasible with no new infra.
- **T1 contract + preview loop already exist:** `ThemePackager` bundle, `DesignTokenGenerator::generateForTheme`, and the layout-aware `studioFrame` showcase — the wizard's "apply proposed theme to a standard preview page" is already built. This is a major head-start T3 was designed to lean on.

## Reuse surface (from Issue Studio) — verdicts

| # | Area | Verdict | Why |
|---|------|---------|-----|
| 1 | `AnthropicGateway::complete(model, systemBlocks, messages, maxTokens, jsonSchema)` | **EXTRACT-SHARED** | Already model- & schema-agnostic; `messages` pass straight to the API so **vision works** by adding image content blocks; `output_config` json_schema forces structured output; retry/backoff/refusal built in. Lift to `app/Services/AI/`, drop "IssueStudio:" log tags. Near-zero cost. |
| 2 | `TokenBudget::assertAvailable/record` | **REUSE-AS-IS** | Keyed only on `tenant_id`, backed by `tenants.monthly_token_budget/…_used/…_reset_at`. Tenant-wide, nothing IS-specific. |
| 3 | JSON contract + repair (`FlatplanEngine::generateWithRepair`) | **EXTRACT loop + BUILD validators** | The decode → validate → feed-errors-back → retry-once → throw loop is generic (worth a shared `SchemaRepairLoop`); the validators are domain rules — the wizard writes its own token-profile validator. |
| 4 | Chat UI (`ChatPane` in IssueStudioPage.tsx) | **EXTRACT shell + BUILD panel** | Bubble list + Enter-to-send input + spinner is clean once decoupled from `useStudioStore`; extract a headless `<ChatShell>`. The brief/right panel is bound to the magazine Brief shape → build a wizard-specific one (token summary + preview). |
| 5 | Session model + zustand store (`StudioSession`, `store.ts`) | **BUILD-NEW from template** | Copy the migration (RLS tenant-isolation policy, jsonb `brief`/`transcript`/`token_usage`, status enum), HasUuids model, and store pattern into a `ThemeWizardSession` sibling — same shape, wizard-specific columns/status. |
| 6 | Controller + routes | **BUILD-NEW same pattern** | Thin controller (delegate → serialize → `RuntimeException`→422); sibling `prefix('theme-wizard')` under the existing `auth:sanctum → tenant.scope → role:admin` stack, `throttle` on AI endpoints. |

**Net:** 2 clean extractions (Gateway, repair-loop) + 1 reuse-as-is (TokenBudget) + 1 shell extraction (chat) + the session/controller/routes copied-as-pattern. No RED foundations — everything the wizard needs exists and is healthy.

## Proposed W1 (next phase — awaiting go-ahead)
1. **Token-profile schema** — a JSON Schema for the wizard's intermediate "design read" (palette with roles bg/text/accent, type character serif/sans + scale + weight rhythm, spacing density, radius/shadow character, layout personality mapping to our 5 layout styles) + a validator + the extract-shared repair round-trip. The profile compiles to a T1 `theme.json` (reusing `ThemePackager`/the semantic token shape).
2. **Curated font allowlist** — open-license Google fonts only, with pairing notes, and a substitution map ("saw a geometric sans → offer Space Grotesk / Archivo"). Never copy a reference site's licensed fonts.
3. Extract `app/Services/AI/AnthropicGateway` + a `SchemaRepairLoop` helper (shared by Issue Studio + wizard; Issue Studio keeps working — re-run its suite to prove it).

Then W2 (reference-analysis pipeline), W3 (chat + preview loop + nudges + compare), W4 (conversation-only path + budgets + logging), W5 (guardrails "inspired not copied" + docs). STOP per phase.
