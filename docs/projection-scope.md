# Projection Scope Wall

The Content Projection Layer builds an internal substrate: one builder, many
filtered views derived from the block tree. The external agent-facing manifest
is its first consumer, not its purpose.

## In scope (this work)

- A semantic descriptor contract (`ProvidesProjection` + `BlockProjection`),
  additive, opt-in, breaking none of the ~100 existing blocks.
- Descriptors for the pilot blocks only: heading, rich-text, image, and the
  4th "article/post wrapper" pilot (see open decision below).
- A pure `ProjectionBuilder` (no I/O) producing the full internal projection.
- Filtered views: Public, Internal, Rag, Inventory.
- Publish-pipeline integration: `index.json` sidecar, `manifest.json`,
  `llms.txt` pointer, optional (default-off) JSON-LD in `<head>`.
- Per-site crawler policy and `robots.txt` generation.
- Parity guard, determinism golden tests, publish regression.

## NOT in scope (this session)

- A writeable API for agents. The projection is read-only, always.
- A per-site MCP endpoint. Separate session, after the projection is stable.
- Sumi (RAG), AI Change Proposals, Export UI, Site Health Ledger. We build the
  substrate, not the consumers.
- Automatic semantic inference for blocks without a descriptor.
- Rolling the descriptor out to all blocks. Only the pilots.
- Any UI for manually editing the projection.
- Modifying `StructuredDataService`'s existing head JSON-LD (Gate 0 decision:
  projection emits sidecars; head JSON-LD stays owned by SDS).
- Writing to `deploy_artifacts` (Gate 0 decision: sidecars ride the existing
  whole-tree symlink swap; the table stays unused).

## What we do NOT promise

Position this as **representational accuracy**, not a traffic channel. Real AI
assistant referral traffic to the average site today is under one percent.
"You'll be found without Google" is false and will backfire. The true claim:
when an assistant talks about this site, it will say correct things.

## Gate 0 decisions (chosen by the product owner)

1. Field-path: no `data.` prefix (`{uuid}#content`) — matches editor `data-sp-field`.
2. JSON-LD head: sidecar-only; head JSON-LD stays with StructuredDataService.
3. 4th pilot: `post-content` block (see open decision in projection-decisions.md).
4. Rollback: whole-tree symlink swap; do not touch `deploy_artifacts`.

## Enablement

Sidecar output is gated behind `settings.crawler_policy.projection_access`
(`public` | `none`, default `none`). A site that has not opted in publishes
byte-for-byte as before. Phase 5 formalises the full `crawler_policy`; Phase 4
reads the `projection_access` key early with a safe default.
