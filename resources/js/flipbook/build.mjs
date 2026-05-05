import * as esbuild from 'esbuild';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';

mkdirSync('dist', { recursive: true });

// ESM bundle
await esbuild.build({
  entryPoints: ['src/index.ts'],
  bundle: true,
  format: 'esm',
  outfile: 'dist/flipbook.esm.js',
  target: 'es2020',
  minify: true,
  sourcemap: true,
});

// IIFE bundle (exposes window.EnsodoFlipbook)
await esbuild.build({
  entryPoints: ['src/index.ts'],
  bundle: true,
  format: 'iife',
  globalName: 'EnsodoFlipbook',
  outfile: 'dist/flipbook.iife.js',
  target: 'es2020',
  minify: true,
  sourcemap: true,
});

// Copy CSS
const css = readFileSync('src/styles.css', 'utf8');
writeFileSync('dist/flipbook.css', css);

console.log('✓ Built flipbook library');
