# Publishing & Deploys (for editors)

How your edits become the live site — and what all the states mean. (For deploy infrastructure — SSH targets, strategies — see [Publishing Pipeline](PUBLISHING.md).)

## The short version

Your live site is **flat static files** rebuilt from the CMS. Nothing dynamic runs on your domain, which is why it's fast and unhackable. Publishing = rendering your content into those files and swapping them in.

## Publishing

- **Auto-publish is on by default**: saving a published page or post triggers a republish automatically. You mostly never press anything.
- The **Publish button** offers **Full Publish** (rebuild everything) and **Quick Publish** (only what changed — the changed pages *plus* everything they affect: blog index, category/tag/author archives, feeds, sitemap, llms.txt).
- **Unpublishing** a page or post removes its files and updates archives on the next publish.

## Staleness — the orange flags

When something a page depends on changes — a global section, a menu, a preset, a renamed slug, a template — affected pages are flagged **stale**. With auto-republish on, the queue rebuilds them in batches; otherwise the Stale Pages screen lets you review and promote a staged batch manually. Renamed URLs clean up their old files automatically.

## The deploy log

Every publish appears under the Publish button with its status, page count, and the **SEO lint** panel: per-page warnings (missing descriptions, multiple h1s, images without alt, thin content, broken internal links, invalid structured data…). Warnings never block a publish — they're your fix-list, ranked by page.

## Rollback & revisions

- Each successful publish keeps its build; **Rollback** re-points the live site to a previous build instantly.
- Each page keeps **revisions** on every save/publish; restore any version from the History panel (a safety snapshot is taken first).

## What ships with every publish

Sitemap, robots.txt, RSS feeds (site + per category), llms.txt, favicon, redirects — all regenerated so they can never drift out of date. See [SEO in Stillopress](SEO-IN-STILLOPRESS.md).
