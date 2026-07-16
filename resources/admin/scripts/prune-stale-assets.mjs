// Prune stale hashed build outputs from public/admin-assets/assets.
//
// Vite is configured with `emptyOutDir: false` (see vite.config.ts) on purpose:
// admin tabs lazy-load chunks AFTER a redeploy, so wiping the output dir 404s
// every in-flight session. The trade-off is that hashed chunks accumulate on
// every deploy — over time the dir grew to hundreds of dead files (dozens of
// copies of the main bundle), bloating disk and the source-export download.
//
// This runs automatically after `npm run build` (npm `postbuild` hook). It
// deletes a file only when BOTH are true:
//   (a) it is NOT referenced by the current build manifest, and
//   (b) it was last modified more than GRACE_DAYS ago.
// Rule (a) protects the freshly-built assets; rule (b) protects chunks from
// recent prior deploys that sessions loaded just before the switch may still
// request. Override the window with ASSET_PRUNE_GRACE_DAYS (default 30).

import { readFileSync, readdirSync, statSync, rmSync, existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(scriptDir, '../../../public/admin-assets');
const assetsDir = path.join(outDir, 'assets');
const graceDays = Number(process.env.ASSET_PRUNE_GRACE_DAYS ?? 30);

if (!existsSync(assetsDir)) {
  console.log('[prune-assets] no assets dir yet — nothing to prune');
  process.exit(0);
}

// (a) Files referenced by the current manifest — always kept, regardless of age.
const keep = new Set();
const manifestPath = path.join(outDir, '.vite', 'manifest.json');
if (existsSync(manifestPath)) {
  const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
  for (const entry of Object.values(manifest)) {
    if (entry.file) keep.add(path.basename(entry.file));
    for (const css of entry.css ?? []) keep.add(path.basename(css));
    for (const asset of entry.assets ?? []) keep.add(path.basename(asset));
  }
} else {
  // Without a manifest we can't tell current from stale — fail safe by pruning
  // on age alone, but warn loudly so a broken build doesn't silently delete.
  console.warn('[prune-assets] no manifest.json found — pruning by age only');
}

const cutoff = Date.now() - graceDays * 24 * 60 * 60 * 1000;
let removed = 0;
let freed = 0;
let kept = 0;

for (const name of readdirSync(assetsDir)) {
  const full = path.join(assetsDir, name);
  const st = statSync(full);
  if (!st.isFile()) continue;

  // A sourcemap (foo.js.map) rides along with its owning chunk (foo.js).
  const owner = name.endsWith('.map') ? name.slice(0, -4) : name;
  if (keep.has(name) || keep.has(owner)) { kept++; continue; }
  if (st.mtimeMs >= cutoff) { kept++; continue; }

  freed += st.size;
  rmSync(full);
  removed++;
}

console.log(
  `[prune-assets] removed ${removed} stale file(s), ` +
  `freed ${(freed / 1048576).toFixed(1)} MB, kept ${kept} (grace ${graceDays}d)`,
);
