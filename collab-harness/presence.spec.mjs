// Collab Phase 2 harness — two authenticated clients join a canvas page's
// presence channel and must see each other. Reverb speaks the Pusher protocol,
// so we drive it with pusher-js directly (no need to boot the whole admin SPA).
//
// Run via collab-harness/run.sh (which starts serve + reverb + seeds first).
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const fx = JSON.parse(fs.readFileSync(path.join(dir, 'fixture.json'), 'utf8'));
const pusherSrc = fs.readFileSync(
  path.join(dir, '..', 'resources', 'admin', 'node_modules', 'pusher-js', 'dist', 'web', 'pusher.js'),
  'utf8',
);

function log(name, cond, extra = '') { console.log(`${cond ? 'PASS' : 'FAIL'}  ${name}${extra ? ' — ' + extra : ''}`); if (!cond) process.exitCode = 1; }

// Log in from within the page — the canonical Sanctum SPA flow (csrf-cookie then
// POST with X-XSRF-TOKEN from document.cookie). The browser sends cookies + Origin
// natively, exactly like the real admin's axios client.
async function login(page, user) {
  const status = await page.evaluate(async ({ email, password }) => {
    const getCookie = (n) => (document.cookie.match('(^|; )' + n + '=([^;]*)') || [])[2];
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    const res = await fetch('/api/v1/auth/login', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(getCookie('XSRF-TOKEN') || ''),
      },
      body: JSON.stringify({ email, password }),
    });
    return res.status;
  }, user);
  if (status !== 200 && status !== 204) throw new Error(`login ${user.email} failed: ${status}`);
}

// Connect pusher-js to Reverb and subscribe the presence channel. Keeps a live
// member-id set on window.__members; resolves once subscription succeeds.
async function joinPresence(page) {
  await page.addScriptTag({ content: pusherSrc });
  return page.evaluate(({ pageId, reverb }) => new Promise((resolve, reject) => {
    const getCookie = (n) => (document.cookie.match('(^|; )' + n + '=([^;]*)') || [])[2];
    const Pusher = window.Pusher;
    Pusher.logToConsole = false;
    const p = new Pusher(reverb.key, {
      cluster: 'mt1',
      wsHost: reverb.host, wsPort: reverb.port, wssPort: reverb.port,
      forceTLS: false, enabledTransports: ['ws'], disableStats: true,
      channelAuthorization: {
        customHandler: ({ socketId, channelName }, callback) => {
          fetch('/broadcasting/auth', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(getCookie('XSRF-TOKEN') || '') },
            body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
          }).then(async (r) => {
            const body = await r.text();
            window.__authDbg = { status: r.status, body: body.slice(0, 300) };
            if (!r.ok) return callback(new Error('auth HTTP ' + r.status), null);
            callback(null, JSON.parse(body));
          }).catch((e) => callback(e, null));
        },
      },
    });
    const ch = p.subscribe('presence-canvas.page.' + pageId);
    window.__members = [];
    const sync = () => { window.__members = []; ch.members.each((m) => window.__members.push(m.id)); };
    ch.bind('pusher:subscription_succeeded', () => { sync(); resolve(window.__members.slice()); });
    ch.bind('pusher:member_added', sync);
    ch.bind('pusher:member_removed', sync);
    ch.bind('pusher:subscription_error', (e) => reject(new Error('subscription_error ' + JSON.stringify(e) + ' auth=' + JSON.stringify(window.__authDbg || null))));
    setTimeout(() => reject(new Error('presence join timeout')), 12000);
  }), { pageId: fx.pageId, reverb: fx.reverb });
}

const browser = await chromium.launch();
try {
  const [alice, bob] = fx.users;

  const ctxA = await browser.newContext();
  const pageA = await ctxA.newPage();
  await pageA.goto(`${fx.appOrigin}/admin`);
  await login(pageA, alice);
  const aFirst = await joinPresence(pageA);
  log('alice joins presence (sees herself)', aFirst.includes(alice.id), `members=${JSON.stringify(aFirst)}`);

  const ctxB = await browser.newContext();
  const pageB = await ctxB.newPage();
  await pageB.goto(`${fx.appOrigin}/admin`);
  await login(pageB, bob);
  const bFirst = await joinPresence(pageB);
  log('bob joins presence (sees both)', bFirst.length === 2 && bFirst.includes(alice.id) && bFirst.includes(bob.id), `members=${JSON.stringify(bFirst)}`);

  // Alice must now observe Bob (member_added propagated)
  const aAfter = await pageA.waitForFunction(() => (window.__members || []).length >= 2, null, { timeout: 8000 })
    .then(() => pageA.evaluate(() => window.__members)).catch(() => []);
  log('alice observes bob join (live member_added)', aAfter.length === 2 && aAfter.includes(bob.id), `members=${JSON.stringify(aAfter)}`);

  // Bob leaves → Alice sees the roster shrink
  await ctxB.close();
  const aFinal = await pageA.waitForFunction(() => (window.__members || []).length === 1, null, { timeout: 8000 })
    .then(() => pageA.evaluate(() => window.__members)).catch(() => (['<did-not-shrink>']));
  log('alice sees bob leave (member_removed)', aFinal.length === 1 && aFinal[0] === alice.id, `members=${JSON.stringify(aFinal)}`);

  await ctxA.close();
} finally {
  await browser.close();
}
console.log('done');
