# Structured Data Reference

Everything Stillopress emits as JSON-LD, when, and from which fields. Users never write schema — it is generated at publish time into **one consolidated `@graph`** in a single `<script type="application/ld+json">` per page.

## The @graph

Emitted by `StructuredDataService::generateGraph` for every published page and post:

| Node | When | Key fields and sources |
|---|---|---|
| `WebPage` | every page | `name` (title), `url` (canonical), `isPartOf` → `WebSite` (site name + URL) |
| `Article` / `NewsArticle` / `BlogPosting` | every post (subtype from `seo_defaults.article_type`, default BlogPosting) | `headline`, `url`, `mainEntityOfPage`, `datePublished` (published_at), **`dateModified` (content_modified_at — real content edits, falls back to updated_at)**, `description` (excerpt), `image` (featured image), `author` → `Person` (post author), `publisher` → `Organization` |
| `Organization` (publisher) | on every post node | `name` + `url` (site), `logo` → ImageObject (Branding → logo), `sameAs` (Branding → social links) |
| `LocalBusiness` (specific subtype) | homepage of sites with a recorded `business_type` | subtype mapped from the business type (HVACBusiness, Plumber, LodgingBusiness, RoofingContractor, …), `description` from business description, `image` from default OG image |
| `BreadcrumbList` | every page and post | Home → parent chain (pages) or Home → category → post; URLs match canonicals |
| `FAQPage` | any page/post with an accordion block holding ≥ 2 Q&A items | `Question`/`Answer` pairs extracted from the block (block-driven schema) |

## Block-driven schema (the `schemaExtractor` pattern)

Blocks can contribute schema the way they contribute references: the accordion block → `FAQPage` is the first implementation. A future recipe block → `Recipe`, gallery → `ImageObject`s, etc. follow the same shape: a per-block extractor invoked during graph assembly, deduplicated into the single `@graph`.

## Date semantics

- `datePublished` = the content's `published_at`.
- `dateModified` = `content_modified_at`, stamped **only** by real content edits (block saves, title/excerpt changes) — never by republish bookkeeping. Sitemap `lastmod` and `article:modified_time` use the same source. If a legacy row has no stamp yet, `updated_at` is the fallback.

## Validation

Publish-time lint soft-validates every JSON-LD block (valid JSON, `@context` present, every node has `@type`) and reports warnings per page in the deploy log. For external verification use Google's Rich Results Test against the published URLs — Article, Breadcrumb, and FAQ are the eligible surfaces.
