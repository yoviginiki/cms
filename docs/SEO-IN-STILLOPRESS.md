# SEO in Stillopress

Stillopress publishes flat static HTML, so most SEO work happens automatically at publish time — you write content, the platform emits correct metadata, structured data, and machine-readable files. This guide covers what is automatic, what you can control, and where.

## Automatic at every publish

- **Per-page head**: `<title>` (site title template), meta description (with a fallback chain — see below), canonical URL, full Open Graph + Twitter Card tags, and `article:*` meta on posts.
- **Structured data**: one consolidated JSON-LD `@graph` per page — WebPage/Article, BreadcrumbList, LocalBusiness (homepage of business sites), FAQPage (from accordion blocks). See [Structured Data Reference](STRUCTURED-DATA-REFERENCE.md).
- **sitemap.xml** with accurate `lastmod` (real content-edit dates, not publish timestamps), **robots.txt**, **llms.txt**, **RSS feeds** (site-wide + per category).
- **Semantic HTML**: `lang` from the site's default language, posts in `<article>`, landmark elements, skip link, image dimensions + lazy loading + LCP priority.

## The description fallback chain

A page/post's meta description is resolved in order: explicit SEO description → post excerpt → first text-bearing block → the site's default description (Site Settings → SEO). Fill the excerpt on posts — it feeds the meta description, RSS, and llms.txt at once.

## Per-page controls (SEO panel in the Page and Post editors)

- **SEO title + meta description** with a live Google-style snippet preview and length indicators; automatic fallbacks are shown greyed.
- **Social image** (OG/Twitter), **canonical override** (for content that canonically lives elsewhere), **robots toggles** (allow indexing / allow following links — both default on).
- **Posts**: author (feeds Article schema) and featured image (social card + schema image).

## Site-level settings (Site Settings → SEO)

- Title template (`{title} | {site_name}`), default meta description, default social image.
- **Search-engine verification** slot (Google Search Console, Bing).
- **Feeds**: excerpt-only or full-content RSS items.
- **AI crawlers**: allowed by default; per-bot opt-out (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, …) writes robots.txt blocks. llms.txt can be disabled.
- A fully custom robots.txt (in site settings) overrides everything verbatim.

## Publish-time SEO lint

Every publish validates each page and reports **warnings (never blocking)** per page in the deploy log (Publish button → Recent Deployments → "SEO lint"):

- Missing/empty/long meta description, missing title or canonical
- Zero or multiple `<h1>`, skipped heading levels
- Images missing alt text or width/height
- Thin content (under 150 words), posts without a featured image
- Broken internal links across the whole built site
- Invalid JSON-LD (parse errors, missing `@context`/`@type`)
- Missing `lang`, missing `<main>` landmark, noindex inconsistencies

Fix what the lint reports and republish — the static output is the single source of truth.

## Writing for AI assistants

See [Writing Content AI Assistants Love to Cite](GEO-AEO-WRITING.md) — the answer-first pattern, question-form headings, and the FAQ block (which emits FAQPage schema automatically).
