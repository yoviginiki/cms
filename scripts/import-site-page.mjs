/**
 * Site Wizard page extractor (NO AI). Loads one page — a live URL, or an HTML
 * file from an extracted design ZIP — in a headless browser and emits:
 *
 *   {
 *     manifest: {page_title, design_read, blocks[]},  // same shape as import-url.mjs
 *     nav:   [{label, href}],                          // header/nav anchors (the page extractor skips these)
 *     links: [href...],                                // same-origin anchors → crawl frontier
 *     style: {...}                                     // computed-style signals for the deterministic theme mapper
 *   }
 *
 * Usage:
 *   node scripts/import-site-page.mjs <url>
 *   node scripts/import-site-page.mjs --dir <extractedZipRoot> --path <relative.html>
 *
 * Local mode serves the extracted ZIP over a throwaway loopback HTTP server so
 * the page renders with its real CSS/geometry (file:// would break relative
 * asset loading and cross-file rules), and blocks all non-loopback requests.
 * SSRF for URL mode is guarded by the PHP caller BEFORE this runs.
 */
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { normalize, join, extname, sep } from 'node:path';
import { chromium } from 'playwright';
import { PAGE_EXTRACTOR, SITE_SIGNALS_EXTRACTOR, autoScroll } from './lib/page-extract.mjs';

const args = process.argv.slice(2);
const fail = (msg) => { process.stderr.write('ERROR: ' + msg.slice(0, 200) + '\n'); process.exit(1); };

let url = null, dir = null, relPath = null;
const localStorageSeed = {};
if (args[0] === '--dir') {
  dir = args[1];
  if (args[2] !== '--path' || !args[3]) fail('local mode needs --dir <root> --path <relative.html>');
  relPath = args[3];
  // --local-storage key=value (repeatable): seed localStorage BEFORE page
  // scripts run — lets client-side language switchers render a chosen locale.
  for (let i = 4; i < args.length - 1; i++) {
    if (args[i] === '--local-storage') {
      const eq = args[i + 1].indexOf('=');
      if (eq > 0) localStorageSeed[args[i + 1].slice(0, eq)] = args[i + 1].slice(eq + 1);
    }
  }
} else {
  url = args[0];
  if (!url || !/^https?:\/\//i.test(url)) fail('a http(s) URL or --dir/--path is required');
}

const MIME = {
  '.html': 'text/html; charset=utf-8', '.htm': 'text/html; charset=utf-8',
  '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.gif': 'image/gif', '.webp': 'image/webp', '.avif': 'image/avif', '.ico': 'image/x-icon',
  '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.otf': 'font/otf',
  '.txt': 'text/plain', '.xml': 'application/xml', '.mp4': 'video/mp4', '.webm': 'video/webm',
};

/** Serve only files inside root with allowlisted extensions — no traversal, no directory listing. */
function startStaticServer(root) {
  return new Promise((resolve, reject) => {
    const server = createServer(async (req, res) => {
      try {
        let pathname = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
        if (pathname.endsWith('/')) pathname += 'index.html';
        const resolved = normalize(join(root, pathname));
        if (!resolved.startsWith(normalize(root) + sep) && resolved !== normalize(root)) {
          res.writeHead(403); res.end(); return;
        }
        const type = MIME[extname(resolved).toLowerCase()];
        if (!type) { res.writeHead(404); res.end(); return; }
        const body = await readFile(resolved);
        res.writeHead(200, { 'content-type': type });
        res.end(body);
      } catch {
        res.writeHead(404); res.end();
      }
    });
    server.listen(0, '127.0.0.1', () => resolve(server));
    server.on('error', reject);
  });
}

let browser, server;
try {
  let target = url;
  if (dir) {
    server = await startStaticServer(dir);
    const port = server.address().port;
    target = `http://127.0.0.1:${port}/` + relPath.split(sep).join('/').replace(/^\/+/, '');
  }

  browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    ignoreHTTPSErrors: true,
    userAgent: 'Mozilla/5.0 (Stillopress Wizard import)',
    reducedMotion: 'reduce',
  });
  if (dir) {
    // A ZIP is untrusted input: never let its markup pull the network. BUT
    // many exports reference their own CSS/JS/images by ABSOLUTE original-
    // domain URLs — blocking those renders the page unstyled and menu-less.
    // So: try to serve any external request from the extracted files by its
    // URL path; only abort when no local file matches.
    const { readFileSync, existsSync, statSync } = await import('node:fs');
    await ctx.route('**', (route) => {
      const u = new URL(route.request().url());
      if (u.hostname === '127.0.0.1') return route.continue();
      let pathname;
      try { pathname = decodeURIComponent(u.pathname); } catch { return route.abort(); }
      const resolved = normalize(join(dir, pathname));
      if (!resolved.startsWith(normalize(dir) + sep)) return route.abort();
      let file = resolved;
      if (existsSync(file) && statSync(file).isDirectory()) file = join(file, 'index.html');
      const type = MIME[extname(file).toLowerCase()];
      if (!type || !existsSync(file)) return route.abort();
      try {
        return route.fulfill({ status: 200, contentType: type, body: readFileSync(file) });
      } catch { return route.abort(); }
    });
  }
  if (Object.keys(localStorageSeed).length) {
    await ctx.addInitScript((seed) => {
      for (const [k, v] of Object.entries(seed)) localStorage.setItem(k, v);
    }, localStorageSeed);
  }
  const page = await ctx.newPage();
  await page.goto(target, { waitUntil: 'networkidle', timeout: 20000 }).catch(async () => {
    await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 15000 });
  });
  await page.waitForTimeout(dir ? 400 : 1000);
  await autoScroll(page);

  // Content parity: collapsed accordions hide real content from the DOM walk,
  // and scroll-reveal animations can leave late sections at opacity 0. Open
  // and settle everything before reading.
  await page.evaluate(() => {
    document.querySelectorAll('details').forEach((d) => { d.open = true; });
    document.querySelectorAll('.reveal, [data-aos], .aos-init, .fade-in, .animate-on-scroll').forEach((el) => {
      el.style.opacity = '1';
      el.style.transform = 'none';
    });
  });
  await page.waitForTimeout(300);

  const manifest = await page.evaluate(PAGE_EXTRACTOR);
  const signals = await page.evaluate(SITE_SIGNALS_EXTRACTOR);
  manifest.design_read = `Imported ${manifest.blocks.length} section(s) from ${manifest.hostname}.`;
  delete manifest.hostname;

  process.stdout.write(JSON.stringify({
    manifest,
    nav: signals.nav,
    links: signals.links,
    style: signals.style,
  }));
  await browser.close();
  if (server) server.close();
} catch (e) {
  if (browser) await browser.close().catch(() => {});
  if (server) try { server.close(); } catch {}
  fail(e && e.message ? e.message : 'import failed');
}
