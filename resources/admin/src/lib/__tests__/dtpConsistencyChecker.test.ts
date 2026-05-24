/**
 * MAG-DTP-CHECK-1 — Consistency checker unit tests.
 *
 * Run with: npx tsx resources/admin/src/lib/__tests__/dtpConsistencyChecker.test.ts
 * or import in any test runner.
 *
 * No test framework dependency — uses assert + console for pass/fail.
 */

import {
  runConsistencyCheck,
  extractEditorDoc,
  extractPayloadDoc,
  labelForPath,
} from '../dtpConsistencyChecker';

let passed = 0;
let failed = 0;

function assert(condition: boolean, name: string) {
  if (condition) {
    passed++;
    console.log(`  \x1b[32m✓\x1b[0m ${name}`);
  } else {
    failed++;
    console.log(`  \x1b[31m✗\x1b[0m ${name}`);
  }
}

// ─── Helpers to build test data ───

function makeEditorPages(frames: any[] = []) {
  return [{
    id: 'page-1', pageNumber: 1, isMaster: false,
    pageSize: { width: 595, height: 842 },
    margins: { top: 36, right: 36, bottom: 36, left: 36 },
    bleed: { top: 9, right: 9, bottom: 9, left: 9 },
    columns: { count: 1, gutter: 12 },
    baselineGrid: { increment: 14, start: 36 },
    backgroundColor: '#ffffff',
    backgroundAssetId: null,
    masterPageId: null,
    spreadWith: null,
    elements: frames,
  }];
}

function makeTextFrame(overrides: any = {}) {
  return {
    id: 'frame-1', type: 'text_frame', name: 'Text',
    x: 36, y: 36, width: 200, height: 100, zIndex: 1, rotation: 0,
    locked: false, visible: true, layerName: null,
    data: { content: '<p>Hello</p>' },
    typography: { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, lineHeight: 1.5, color: '#000000', textAlign: 'left' },
    style: { fill: null, stroke: null, opacity: 1 },
    textWrap: null,
    threadId: null, threadOrder: null,
    pageNumber: 1, onMaster: false,
    positionMode: 'free', spanMode: 'page',
    parentId: null, children: [], responsiveOverrides: {},
    scaleX: 1, scaleY: 1,
    ...overrides,
  };
}

function makeImageFrame(overrides: any = {}) {
  return {
    id: 'frame-img-1', type: 'image_frame', name: 'Image',
    x: 100, y: 100, width: 300, height: 200, zIndex: 2, rotation: 0,
    locked: false, visible: true, layerName: null,
    data: { src: '/storage/photo.jpg', alt: 'Photo', fit: 'cover', focalPoint: { x: 50, y: 50 }, opacity: 100 },
    typography: null,
    style: { fill: null, stroke: null, opacity: 1 },
    textWrap: null,
    threadId: null, threadOrder: null,
    pageNumber: 1, onMaster: false,
    positionMode: 'free', spanMode: 'page',
    parentId: null, children: [], responsiveOverrides: {},
    scaleX: 1, scaleY: 1,
    ...overrides,
  };
}

function makePayload(pages: any[], frames: any[], meta: any = {}) {
  return {
    spreads: pages.map((p: any, i: number) => ({ id: `spread-${i}`, spread_index: i })),
    pages: pages.map((p: any, i: number) => ({
      id: p.id, spread_id: `spread-${i}`, page_index: i,
      width: p.width || 595, height: p.height || 842,
      margins: p.margins || { top: 36, right: 36, bottom: 36, left: 36 },
      background: { color: p.backgroundColor || '#ffffff' },
      master_page_id: null,
    })),
    frames,
    meta,
  };
}

function makePayloadFrame(overrides: any = {}) {
  return {
    id: 'frame-1', page_id: 'page-1', frame_type: 'text',
    x: 36, y: 36, width: 200, height: 100, z_index: 1, rotation: 0,
    visible: true, locked: false,
    content: { html: '<p>Hello</p>' },
    style: {},
    metadata: { _typography: { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, lineHeight: 1.5, color: '#000000', textAlign: 'left' }, threadId: null, threadOrder: null },
    ...overrides,
  };
}

// ─── Tests ───

console.log('\nMAG-DTP-CHECK-1 Consistency Checker Tests\n');

// Test 1: PASS when all stages match
console.log('1. Pass when all stages match');
{
  const pages = makeEditorPages([makeTextFrame()]);
  const issueSettings = { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  const plFrame = makePayloadFrame();
  const payload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [plFrame], { issueSettings });
  const plDoc = extractPayloadDoc(payload, issueSettings);
  const ldDoc = extractPayloadDoc(payload, issueSettings); // loaded = same as payload

  const result = runConsistencyCheck(edDoc, plDoc, ldDoc, null);
  assert(result.status === 'pass', 'status is pass');
  assert(result.summary.failures === 0, 'no failures');
  assert(result.summary.warnings === 0, 'no warnings');
}

// Test 2: Detect field missing in payload
console.log('\n2. Detect field missing in payload');
{
  const pages = makeEditorPages([makeTextFrame()]);
  const issueSettings = { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  // Payload frame missing html content
  const plFrame = makePayloadFrame({ content: {} });
  const payload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [plFrame], { issueSettings });
  const plDoc = extractPayloadDoc(payload, issueSettings);

  const result = runConsistencyCheck(edDoc, plDoc, null, null);
  const htmlIssue = result.issues.find(i => i.path.includes('content.html'));
  assert(htmlIssue != null, 'content.html mismatch detected');
  assert(htmlIssue?.code === 'field_missing_in_payload', 'correct issue code');
}

// Test 3: Detect field lost after save/load
console.log('\n3. Detect field lost after save/load');
{
  const pages = makeEditorPages([makeTextFrame()]);
  const issueSettings = { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  const plFrame = makePayloadFrame();
  const payload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [plFrame], { issueSettings });
  const plDoc = extractPayloadDoc(payload, issueSettings);

  // Loaded has html stripped
  const ldPayload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [makePayloadFrame({ content: {} })], { issueSettings });
  const ldDoc = extractPayloadDoc(ldPayload, issueSettings);

  const result = runConsistencyCheck(edDoc, plDoc, ldDoc, null);
  const lostIssue = result.issues.find(i => i.code === 'field_lost_after_save');
  assert(lostIssue != null, 'field_lost_after_save detected');
}

// Test 4: Detect typography color lost
console.log('\n4. Detect typography color lost');
{
  const pages = makeEditorPages([makeTextFrame({ typography: { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, lineHeight: 1.5, color: '#ff0000', textAlign: 'left' } })]);
  const issueSettings = { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  const plFrame = makePayloadFrame({ metadata: { _typography: { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, lineHeight: 1.5, color: '#ff0000', textAlign: 'left' } } });
  const payload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [plFrame], { issueSettings });
  const plDoc = extractPayloadDoc(payload, issueSettings);

  // Loaded: typography color stripped
  const ldFrame = makePayloadFrame({ metadata: { _typography: { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, lineHeight: 1.5, textAlign: 'left' } } });
  const ldPayload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [ldFrame], { issueSettings });
  const ldDoc = extractPayloadDoc(ldPayload, issueSettings);

  const result = runConsistencyCheck(edDoc, plDoc, ldDoc, null);
  const colorIssue = result.issues.find(i => i.path.includes('typography.color'));
  assert(colorIssue != null, 'typography.color loss detected');
  assert(colorIssue?.code === 'typography_not_rendered_in_viewer', 'correct code for typography loss');
}

// Test 5: Detect image src lost
console.log('\n5. Detect image src lost');
{
  const pages = makeEditorPages([makeImageFrame()]);
  const issueSettings = { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  const plImgFrame = {
    id: 'frame-img-1', page_id: 'page-1', frame_type: 'image',
    x: 100, y: 100, width: 300, height: 200, z_index: 2,
    visible: true, locked: false,
    content: { src: '/storage/photo.jpg', alt: 'Photo', fitMode: 'cover', focalPoint: { x: 50, y: 50 }, opacity: 100 },
    style: {}, metadata: {},
  };
  const payload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [plImgFrame], { issueSettings });
  const plDoc = extractPayloadDoc(payload, issueSettings);

  // Loaded: image src empty
  const ldImgFrame = { ...plImgFrame, content: { src: '', alt: 'Photo', fitMode: 'cover' } };
  const ldPayload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [ldImgFrame], { issueSettings });
  const ldDoc = extractPayloadDoc(ldPayload, issueSettings);

  const result = runConsistencyCheck(edDoc, plDoc, ldDoc, null);
  const srcIssue = result.issues.find(i => i.path.includes('content.src'));
  assert(srcIssue != null, 'image src loss detected');
}

// Test 6: Detect layout mode mismatch
console.log('\n6. Detect layout mode mismatch');
{
  const pages = makeEditorPages([]);
  const issueSettings = { layoutMode: 'book', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  // Payload has different layout mode
  const payload = makePayload([{ id: 'page-1', width: 595, height: 842, backgroundColor: '#ffffff' }], [], { issueSettings: { layoutMode: 'single' } });
  const plDoc = extractPayloadDoc(payload, issueSettings);

  const result = runConsistencyCheck(edDoc, plDoc, null, null);
  const modeIssue = result.issues.find(i => i.code === 'layout_mode_mismatch');
  assert(modeIssue != null, 'layout_mode_mismatch detected');
  assert(modeIssue?.severity === 'error', 'severity is error');
}

// Test 7: Detect viewer mismatch
console.log('\n7. Detect viewer mismatch');
{
  const pages = makeEditorPages([makeTextFrame(), makeImageFrame()]);
  const issueSettings = { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' };
  const edDoc = extractEditorDoc(pages, issueSettings);

  // Viewer model missing the image frame
  const viewerModel = {
    pages: [{
      id: 'page-1',
      background_color: '#ffffff',
      elements: [
        { id: 'frame-1', type: 'text', content: { html: '<p>Hello</p>' }, style: {} },
        // frame-img-1 missing from viewer
      ],
    }],
  };

  const result = runConsistencyCheck(edDoc, null, null, viewerModel);
  const viewerIssue = result.issues.find(i => i.code === 'viewer_render_mismatch');
  assert(viewerIssue != null, 'viewer_render_mismatch detected');
  assert(result.summary.viewerMismatches > 0, 'viewerMismatches count > 0');
}

// Test 8: Human labels map correctly
console.log('\n8. Human labels map correctly');
{
  assert(labelForPath('settings.layoutMode') === 'Issue layout mode', 'layoutMode label');
  assert(labelForPath('typography.color') === 'Text color', 'typography.color label');
  assert(labelForPath('content.src') === 'Image source', 'content.src label');
  assert(labelForPath('style.borderWidth') === 'Border width', 'borderWidth label');
  assert(labelForPath('style.boxShadow') === 'Box shadow', 'boxShadow label');
  assert(labelForPath('threadId') === 'Text continuation link', 'threadId label');
  assert(labelForPath('content.fitMode') === 'Image fit mode', 'fitMode label');
}

// ─── Summary ───

console.log(`\n${'─'.repeat(40)}`);
console.log(`Results: ${passed} passed, ${failed} failed`);
if (failed > 0) {
  console.log('\x1b[31mFAILED\x1b[0m');
  process.exit(1);
} else {
  console.log('\x1b[32mALL PASSED\x1b[0m');
}
