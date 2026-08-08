# Crawler Policy (Phase 5)

Per-site policy governing how bots may access the site and its projection.
Stored under `settings.crawler_policy`:

```
crawler_policy: {
  search_engines:    allow,          // classic SEO crawlers (Googlebot, Bingbot, …)
  ai_training:       allow | deny,   // bots building training corpora (GPTBot, CCBot, …)
  ai_retrieval:      allow | deny,   // on-demand answer bots (PerplexityBot, ChatGPT-User, …)
  projection_access: public | none   // whether index.json / manifest.json sidecars are published
}
```

All keys default to the permissive value, so a site without a `crawler_policy`
behaves exactly as before (byte-identical `robots.txt`).

## Why training vs retrieval are separate

The distinction is essential. Many owners want to be **cited in answers**
(retrieval) but not **used to train models** (training). A single AI on/off
switch cannot express that. The two keys map to two disjoint bot lists in
`RobotsGenerator` (`AI_TRAINING_BOTS`, `AI_RETRIEVAL_BOTS`).

## robots.txt is a request, not a wall

**The UI copy for this setting MUST say so plainly.** `robots.txt` is a
politeness protocol: compliant bots honour it, non-compliant bots ignore it —
and a bot that ignores the rules will ignore this too. We do not promise control
we do not have. This is a product principle, not a UX detail. (The settings UI
itself is out of scope for this session — the substrate is built here; the copy
requirement is recorded so the UI honours it.)

## projection_access

`public` publishes the machine-readable sidecars (`index.json`, `manifest.json`)
and stores the internal projection snapshot. `none` (default) publishes neither —
the projection is fully disabled and the publish output is byte-identical to a
pre–projection build.
