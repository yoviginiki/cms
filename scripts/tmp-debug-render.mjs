import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { normalize, join, extname, sep } from 'node:path';
import { chromium } from 'playwright';

const dir = process.argv[2];
const rel = process.argv[3];
const MIME = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.webp': 'image/webp' };

const server = await new Promise((res) => {
  const s = createServer(async (req, r) => {
    try {
      let p = decodeURIComponent(new URL(req.url, 'http://x').pathname);
      if (p.endsWith('/')) p += 'index.html';
      const f = normalize(join(dir, p));
      const body = await readFile(f);
      const t = MIME[extname(f).toLowerCase()] || 'application/octet-stream';
      r.writeHead(200, { 'content-type': t }); r.end(body);
    } catch { try { r.writeHead(404); } catch {} r.end(); }
  });
  s.listen(0, '127.0.0.1', () => res(s));
});

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const page = await browser.newPage();
page.on('pageerror', (e) => console.log('PAGEERROR:', String(e).slice(0, 300)));
page.on('console', (m) => m.type() === 'error' && console.log('CONSOLE:', m.text().slice(0, 200)));
await page.goto(`http://127.0.0.1:${server.address().port}/${rel}`, { waitUntil: 'networkidle', timeout: 20000 }).catch((e) => console.log('GOTO:', String(e).slice(0, 150)));
await page.waitForTimeout(1500);
const info = await page.evaluate(() => {
  const h1 = document.querySelector('h1');
  const r = h1?.getBoundingClientRect();
  const s = h1 ? getComputedStyle(h1) : null;
  const reveals = Array.from(document.querySelectorAll('.reveal')).slice(0, 3).map((el) => {
    const st = getComputedStyle(el);
    return { opacity: st.opacity, display: st.display, cls: el.className };
  });
  return {
    title: document.title,
    h1: h1 ? { text: h1.innerText.slice(0, 60), w: r.width, h: r.height, opacity: s.opacity, display: s.display } : null,
    bodyChildren: document.body.children.length,
    reveals,
  };
});
console.log(JSON.stringify(info, null, 1));
await browser.close(); server.close();
