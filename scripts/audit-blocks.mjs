#!/usr/bin/env node
/**
 * Block Audit Script
 * Checks consistency across three layers: Frontend (React) → Backend (PHP) → Rendering (Blade)
 *
 * Usage: node scripts/audit-blocks.mjs [--json-only] [--no-color]
 * Output: storage/app/block-audit.json + terminal table
 * Exit:   0 if all blocks COMPLETE, 1 if any incomplete
 */

import { readdirSync, existsSync, readFileSync, writeFileSync, mkdirSync } from 'fs';
import { join, basename, resolve } from 'path';

const ROOT = resolve(import.meta.dirname, '..');
const FRONTEND_DIR = join(ROOT, 'resources/admin/src/components/blocks');
const BLADE_DIR = join(ROOT, 'resources/views/blocks');
const PHP_DIR = join(ROOT, 'app/Domain/Blocks/Definitions');
const FRONTEND_INDEX = join(FRONTEND_DIR, 'index.ts');
const OUTPUT_PATH = join(ROOT, 'storage/app/block-audit.json');

const args = process.argv.slice(2);
const JSON_ONLY = args.includes('--json-only');
const NO_COLOR = args.includes('--no-color');

// ─── Color helpers ───
const c = NO_COLOR ? { r: '', g: '', y: '', c: '', m: '', n: '' } : {
  r: '\x1b[31m', g: '\x1b[32m', y: '\x1b[33m',
  c: '\x1b[36m', m: '\x1b[35m', n: '\x1b[0m'
};

// ─── Utility: kebab-case to StudlyCase ───
function toStudly(type) {
  return type
    .split(/[-_]/)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join('');
}

// ─── Strip JS comments ───
function stripComments(src) {
  return src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
}

// ─── Read frontend index imports ───
function getRegisteredImports() {
  if (!existsSync(FRONTEND_INDEX)) return new Set();
  const raw = readFileSync(FRONTEND_INDEX, 'utf8');
  const content = stripComments(raw);
  const imports = new Set();
  for (const match of content.matchAll(/^\s*import\s+['"]\.\/([\w-]+)['"]\s*;?\s*$/gm)) {
    imports.add(match[1]);
  }
  return imports;
}

// ─── Read PHP definitions and extract type() values ───
function getPhpDefinitions() {
  const defs = new Map(); // type -> className
  if (!existsSync(PHP_DIR)) return defs;

  for (const file of readdirSync(PHP_DIR)) {
    if (!file.endsWith('.php') || file === 'BlockDefinition.php') continue;
    const className = file.replace('.php', '');
    const filePath = join(PHP_DIR, file);
    const content = readFileSync(filePath, 'utf8');

    // Extract type() return value
    const typeMatch = content.match(/function\s+type\(\).*?return\s+['"]([^'"]+)['"]/s);
    if (typeMatch) {
      defs.set(typeMatch[1], className);
    } else {
      // Fallback: derive type from class name
      const derived = className.replace('BlockDefinition', '').toLowerCase();
      defs.set(derived, className);
    }
  }
  return defs;
}

// ─── Get all blade templates ───
function getBladeTemplates() {
  const templates = new Set();
  if (!existsSync(BLADE_DIR)) return templates;
  for (const file of readdirSync(BLADE_DIR)) {
    if (file.endsWith('.blade.php')) {
      templates.add(file.replace('.blade.php', ''));
    }
  }
  return templates;
}

// ─── Get frontend block folders ───
function getFrontendBlocks() {
  const blocks = [];
  if (!existsSync(FRONTEND_DIR)) return blocks;
  for (const entry of readdirSync(FRONTEND_DIR, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      blocks.push(entry.name);
    }
  }
  return blocks.sort();
}

// ─── Determine status ───
function determineStatus(entry) {
  const { hasFrontendFolder, importedInFrontendIndex, hasDefinitionTs, hasEditorTsx, hasPreviewTsx, hasIndexTs, hasBladeTemplate, hasBackendDefinition } = entry;

  if (!hasFrontendFolder && hasBladeTemplate && !hasBackendDefinition) return 'ORPHAN_BLADE';
  if (!hasFrontendFolder && hasBackendDefinition) return 'ORPHAN_BACKEND';
  if (!hasFrontendFolder) return 'UNKNOWN';

  const frontendFilesComplete = hasDefinitionTs && hasEditorTsx && hasPreviewTsx && hasIndexTs;

  if (!frontendFilesComplete) return 'MISSING_FRONTEND_FILE';
  if (!importedInFrontendIndex) return 'NOT_REGISTERED';
  if (!hasBladeTemplate) return 'MISSING_BLADE';
  if (!hasBackendDefinition) return 'MISSING_BACKEND';

  return 'COMPLETE';
}

// ─── Main ───
function audit() {
  const registeredImports = getRegisteredImports();
  const phpDefs = getPhpDefinitions();
  const bladeTemplates = getBladeTemplates();
  const frontendBlocks = getFrontendBlocks();

  // Collect all known types
  const allTypes = new Set([
    ...frontendBlocks,
    ...bladeTemplates,
    ...phpDefs.keys(),
  ]);

  const results = [];

  for (const type of [...allTypes].sort()) {
    const hasFrontendFolder = frontendBlocks.includes(type);
    const importedInFrontendIndex = registeredImports.has(type);

    const blockDir = join(FRONTEND_DIR, type);
    const hasDefinitionTs = hasFrontendFolder && existsSync(join(blockDir, 'definition.ts'));
    const hasEditorTsx = hasFrontendFolder && existsSync(join(blockDir, 'Editor.tsx'));
    const hasPreviewTsx = hasFrontendFolder && existsSync(join(blockDir, 'Preview.tsx'));
    const hasIndexTs = hasFrontendFolder && existsSync(join(blockDir, 'index.ts'));
    const hasBladeTemplate = bladeTemplates.has(type);
    const hasBackendDefinition = phpDefs.has(type);

    const entry = {
      type,
      hasFrontendFolder,
      importedInFrontendIndex,
      hasDefinitionTs,
      hasEditorTsx,
      hasPreviewTsx,
      hasIndexTs,
      hasBladeTemplate,
      hasBackendDefinition,
      backendClass: phpDefs.get(type) || null,
      status: '', // set below
    };

    entry.status = determineStatus(entry);
    results.push(entry);
  }

  // Summary
  const summary = {
    total: results.length,
    complete: results.filter(r => r.status === 'COMPLETE').length,
    incomplete: results.filter(r => r.status !== 'COMPLETE').length,
    byStatus: {},
  };

  for (const r of results) {
    summary.byStatus[r.status] = (summary.byStatus[r.status] || 0) + 1;
  }

  return { results, summary };
}

// ─── Terminal output ───
function printTable(results, summary) {
  const statusColor = {
    COMPLETE: c.g,
    MISSING_BACKEND: c.y,
    MISSING_BLADE: c.r,
    MISSING_FRONTEND_FILE: c.r,
    NOT_REGISTERED: c.r,
    ORPHAN_BLADE: c.m,
    ORPHAN_BACKEND: c.m,
    UNKNOWN: c.r,
  };

  console.log(`\n${c.c}═══════════════════════════════════════════════════════════════════${c.n}`);
  console.log(`${c.c}  Block Audit Report${c.n}`);
  console.log(`${c.c}═══════════════════════════════════════════════════════════════════${c.n}\n`);

  // Table header
  const hdr = `${'Type'.padEnd(20)} ${'Status'.padEnd(22)} ${'FE'.padEnd(4)} ${'Reg'.padEnd(4)} ${'Blade'.padEnd(6)} ${'PHP'.padEnd(4)} Missing`;
  console.log(`  ${c.c}${hdr}${c.n}`);
  console.log(`  ${'─'.repeat(80)}`);

  for (const r of results) {
    const col = statusColor[r.status] || c.n;
    const fe = r.hasFrontendFolder ? '✓' : '✗';
    const reg = r.importedInFrontendIndex ? '✓' : '✗';
    const blade = r.hasBladeTemplate ? '✓' : '✗';
    const php = r.hasBackendDefinition ? '✓' : '✗';

    const missing = [];
    if (!r.hasDefinitionTs && r.hasFrontendFolder) missing.push('definition.ts');
    if (!r.hasEditorTsx && r.hasFrontendFolder) missing.push('Editor.tsx');
    if (!r.hasPreviewTsx && r.hasFrontendFolder) missing.push('Preview.tsx');
    if (!r.hasIndexTs && r.hasFrontendFolder) missing.push('index.ts');
    if (!r.hasBladeTemplate && r.hasFrontendFolder) missing.push('blade');
    if (!r.hasBackendDefinition && r.hasFrontendFolder) missing.push('PHP def');
    if (!r.importedInFrontendIndex && r.hasFrontendFolder) missing.push('registry');

    const line = `${r.type.padEnd(20)} ${col}${r.status.padEnd(22)}${c.n} ${fe.padEnd(4)} ${reg.padEnd(4)} ${blade.padEnd(6)} ${php.padEnd(4)} ${missing.join(', ')}`;
    console.log(`  ${line}`);
  }

  // Summary
  console.log(`\n${c.c}═══════════════════════════════════════════════════════════════════${c.n}`);
  console.log(`  Total: ${summary.total}`);
  console.log(`  ${c.g}Complete: ${summary.complete}${c.n}`);
  console.log(`  ${c.y}Incomplete: ${summary.incomplete}${c.n}`);
  console.log('');
  for (const [status, count] of Object.entries(summary.byStatus).sort()) {
    const col = statusColor[status] || c.n;
    console.log(`    ${col}${status}: ${count}${c.n}`);
  }
  console.log(`\n${c.c}═══════════════════════════════════════════════════════════════════${c.n}\n`);
}

// ─── Run ───
const { results, summary } = audit();

// Write JSON
mkdirSync(join(ROOT, 'storage/app'), { recursive: true });
const report = {
  generatedAt: new Date().toISOString(),
  summary,
  blocks: results,
};
writeFileSync(OUTPUT_PATH, JSON.stringify(report, null, 2));

// Print table
if (!JSON_ONLY) {
  printTable(results, summary);
  console.log(`  JSON report: storage/app/block-audit.json\n`);
}

// Exit code
process.exit(summary.incomplete > 0 ? 1 : 0);
