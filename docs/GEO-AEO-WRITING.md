# Writing Content AI Assistants Love to Cite (GEO/AEO)

Stillopress publishes flat static HTML — the cleanest possible input for both search engines and AI assistants. This guide covers the authoring pattern that gets your content *cited*, and the site settings that control AI access.

## The answer-first pattern

AI assistants extract and cite content that answers questions directly. Structure every page and post so the answer comes before the elaboration:

1. **Lead with the direct answer.** The first paragraph under any heading should answer the heading's implied question in one or two sentences. Elaborate after, never before.
2. **Use question-form headings.** "How much does a chimney inspection cost?" beats "Pricing considerations". Heading blocks support h2–h6 — keep one h1 (the page title) and step down levels without skipping.
3. **One idea per section.** Short sections with descriptive headings are extraction-friendly; a 1,000-word wall under one heading is not.
4. **Use the FAQ block (accordion).** Any accordion block with two or more question/answer items automatically publishes `FAQPage` structured data — no configuration. Put the questions customers actually ask, with complete self-contained answers.
5. **Fill excerpts.** A post's excerpt becomes its meta description fallback, its RSS description, and its llms.txt notes — one field, three surfaces.

## What Stillopress generates for you

At every publish, each site ships:

- **`/llms.txt`** — a machine-readable markdown guide to the site (name, summary, published pages and recent posts with descriptions), per the [llms.txt proposal](https://llmstxt.org/) (published 2024-09-03; no formal version number — spec verified 2026-07-12). Toggle in **Site Settings → SEO**.
- **RSS feeds** — `/feed.xml` for the site plus `/{category}/feed.xml` per category. Choose excerpt-only or full-content items in **Site Settings → SEO** (full content uses `content:encoded`).
- **AI-crawler controls** — robots.txt allows AI crawlers by default (being cited is distribution). Opt out per bot (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and others) in **Site Settings → SEO**; each unchecked bot gets a `User-agent` / `Disallow: /` block. A custom robots.txt in settings overrides everything verbatim.
- **Accurate `dateModified`** — sitemap `lastmod`, Article `dateModified`, and `article:modified_time` reflect real content edits (block saves, title/excerpt changes), not publish-run timestamps. AI systems and Google both use freshness signals; inflated ones erode trust.

## Checklist before publishing

- [ ] One h1; question-form h2s; no skipped heading levels
- [ ] First paragraph under each heading answers the heading
- [ ] FAQ (accordion) block with ≥2 real questions on key pages
- [ ] Excerpt filled on every post
- [ ] Meta description present (or a strong first text block for the auto-fallback)
