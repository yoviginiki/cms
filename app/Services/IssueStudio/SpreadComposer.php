<?php

namespace App\Services\IssueStudio;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpDocumentService;
use App\Domain\Magazine\Services\MagazineReferenceExtractor;
use App\Domain\Publishing\Services\SanitizationService;
use App\Domain\References\Services\ReferenceRecorder;
use App\Models\IssueStudio\StudioSession;
use App\Models\IssueStudio\StudioSpread;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a validated spread document (SpreadElementContract form) into real
 * Magazine editor data: creates/updates the session's magazine issue via
 * DtpDocumentService (atomic full replace), records entity references, and
 * keeps spread page ownership derived from page_index (cover = 0, spread n
 * = 2n-1 and 2n) since saves remap all ids.
 */
class SpreadComposer
{
    public function __construct(
        private DtpDocumentService $documents,
        private SanitizationService $sanitizer,
    ) {
    }

    public function ensureIssue(StudioSession $session): MagazineIssue
    {
        if ($session->magazine_issue_id) {
            $issue = MagazineIssue::find($session->magazine_issue_id);
            if ($issue) {
                return $issue;
            }
        }

        $brief = $session->brief ?? [];
        $issue = MagazineIssue::create([
            'tenant_id' => $session->tenant_id,
            'site_id' => $session->site_id,
            'title' => $brief['working_title'] ?? ($session->title ?? 'Untitled issue'),
            'subtitle' => $brief['topic'] ?? null,
            'status' => 'draft',
            'created_by' => $session->user_id,
            'target_page_count' => count($session->flatplan['spreads'] ?? []) * 2,
        ]);

        $session->magazine_issue_id = $issue->id;
        $session->save();

        return $issue;
    }

    /**
     * Write one generated spread into the issue document (replacing its
     * previous pages if regenerating). Returns the new page ids owned by
     * this spread.
     *
     * @param array $doc validated SpreadElementContract document
     * @return string[] page ids
     */
    public function writeSpread(StudioSession $session, StudioSpread $spread, array $doc): array
    {
        $issue = $this->ensureIssue($session);
        $payload = $this->currentPayload($issue);

        [$indexes, $sides] = $this->pageSlots($spread->position);

        // drop this spread's previous pages/layers/frames (by page_index)
        $dropPageIds = [];
        foreach ($payload['pages'] as $page) {
            if (in_array($page['page_index'], $indexes, true)) {
                $dropPageIds[] = $page['id'];
            }
        }
        $payload['pages'] = array_values(array_filter($payload['pages'], fn ($p) => !in_array($p['id'], $dropPageIds, true)));
        $payload['layers'] = array_values(array_filter($payload['layers'], fn ($l) => !in_array($l['page_id'] ?? null, $dropPageIds, true)));
        $payload['frames'] = array_values(array_filter($payload['frames'], fn ($f) => !in_array($f['page_id'] ?? null, $dropPageIds, true)));
        $payload['spreads'] = array_values(array_filter($payload['spreads'], fn ($s) => ($s['spread_index'] ?? null) !== $spread->position));

        // build the new fragment
        $spreadClientId = 'is-s-' . $spread->position;
        $payload['spreads'][] = [
            'id' => $spreadClientId,
            'spread_index' => $spread->position,
            'name' => $spread->working_title ?: "Spread {$spread->position}",
            'metadata' => ['_issueStudio' => ['spread_id' => $spread->id, 'pattern' => $spread->pattern]],
        ];

        foreach (array_values($doc['pages']) as $i => $page) {
            $pageId = 'is-p-' . $spread->position . '-' . $i;
            $layerId = 'is-l-' . $spread->position . '-' . $i;

            $payload['pages'][] = [
                'id' => $pageId,
                'spread_id' => $spreadClientId,
                'page_index' => $indexes[$i],
                'side' => $sides[$i],
                'width' => 595,
                'height' => 842,
                'margins' => ['top' => 36, 'right' => 36, 'bottom' => 36, 'left' => 36],
                'background' => ['color' => '#ffffff'],
                'metadata' => ['_issueStudio' => ['spread_id' => $spread->id]],
            ];
            $payload['layers'][] = [
                'id' => $layerId,
                'page_id' => $pageId,
                'name' => 'Layer 1',
                'layer_order' => 0,
            ];

            foreach ($page['elements'] as $el) {
                $payload['frames'][] = $this->elementToFrame($session, $el, $pageId, $spreadClientId, $layerId);
            }
        }

        usort($payload['spreads'], fn ($a, $b) => $a['spread_index'] <=> $b['spread_index']);
        usort($payload['pages'], fn ($a, $b) => $a['page_index'] <=> $b['page_index']);

        $saved = $this->documents->saveDocument($issue, $payload);
        $this->recordReferences($session, $spread, $issue);

        // resolve the new server ids for this spread's pages
        $pageIds = [];
        foreach ($saved['pages'] ?? [] as $page) {
            if (in_array($page['page_index'], $indexes, true)) {
                $pageIds[] = (string) $page['id'];
            }
        }

        return $pageIds;
    }

    /**
     * Every save remaps ALL ids — refresh page_ids on every non-pending
     * spread from the current document (ownership derives from page_index).
     */
    public function syncPageIds(StudioSession $session): void
    {
        if (!$session->magazine_issue_id) {
            return;
        }
        $issue = MagazineIssue::find($session->magazine_issue_id);
        if (!$issue) {
            return;
        }

        $byIndex = [];
        foreach ($this->documents->loadDocument($issue)['pages'] as $page) {
            $byIndex[(int) $page['page_index']] = (string) $page['id'];
        }

        foreach ($session->spreads()->where('status', '!=', 'pending')->get() as $spread) {
            [$indexes] = $this->pageSlots($spread->position);
            $ids = array_values(array_filter(array_map(fn ($i) => $byIndex[$i] ?? null, $indexes)));
            $spread->update(['page_ids' => $ids]);
        }
    }

    /** Extract this spread's current document (contract form) for revision context. */
    public function readSpread(StudioSession $session, StudioSpread $spread): array
    {
        $issue = $this->ensureIssue($session);
        $docData = $this->documents->loadDocument($issue);
        [$indexes] = $this->pageSlots($spread->position);

        $pages = [];
        foreach ($docData['pages'] as $page) {
            if (!in_array($page['page_index'], $indexes, true)) {
                continue;
            }
            $elements = [];
            foreach ($docData['frames'] as $frame) {
                if (($frame['page_id'] ?? null) !== $page['id']) {
                    continue;
                }
                $elements[] = [
                    'type' => $frame['metadata']['_magType'] ?? $frame['frame_type'],
                    'x' => $frame['x'], 'y' => $frame['y'], 'w' => $frame['width'], 'h' => $frame['height'],
                    'rotation' => $frame['rotation'] ?? 0,
                    'z' => $frame['z_index'] ?? 0,
                    'content' => $frame['content'],
                ];
            }
            $pages[] = ['side' => $page['side'], 'elements' => $elements];
        }

        return $pages;
    }

    /** page_index slots + sides owned by a studio spread position. */
    public function pageSlots(int $position): array
    {
        if ($position === 0) {
            return [[0], ['single']];
        }

        return [[2 * $position - 1, 2 * $position], ['left', 'right']];
    }

    private function currentPayload(MagazineIssue $issue): array
    {
        $doc = $this->documents->loadDocument($issue);

        return [
            'spreads' => $doc['spreads'] instanceof \Illuminate\Support\Collection ? $doc['spreads']->toArray() : (array) $doc['spreads'],
            'pages' => $doc['pages'] instanceof \Illuminate\Support\Collection ? $doc['pages']->toArray() : (array) $doc['pages'],
            'layers' => $doc['layers'] instanceof \Illuminate\Support\Collection ? $doc['layers']->toArray() : (array) $doc['layers'],
            'frames' => $doc['frames'] instanceof \Illuminate\Support\Collection ? $doc['frames']->toArray() : (array) $doc['frames'],
            'asset_references' => [],
            'meta' => ($doc['meta'] ?? []) ?: ['issueSettings' => ['layoutMode' => 'book', 'coverMode' => 'standalone']],
        ];
    }

    private function elementToFrame(StudioSession $session, array $el, string $pageId, string $spreadId, string $layerId): array
    {
        $type = $el['type'];
        $frameType = SpreadElementContract::TYPE_MAP[$type];

        $content = [];
        $typography = [];
        $safeColor = fn (?string $c) => (is_string($c) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $c)) ? $c : null;

        if (in_array($type, SpreadElementContract::TEXT_TYPES, true)) {
            $content['html'] = $this->sanitizer->purifyMagazine((string) $el['html']);
            if ($type === 'pullquote_frame') {
                $content['attribution'] = mb_substr(strip_tags((string) ($el['attribution'] ?? '')), 0, 200);
            }
            if (!empty($el['columns']) && $type === 'text_frame') {
                // renderer + editor key is columnsInFrame
                $content['columnsInFrame'] = max(1, min(3, (int) $el['columns']));
                $content['columnGap'] = 12;
            }
            // renderer + editor read typography from metadata._typography
            $typography = array_filter([
                'fontSize' => isset($el['font_size']) ? max(6, min(120, (float) $el['font_size'])) : null,
                'lineHeight' => isset($el['line_height']) ? max(0.8, min(3, (float) $el['line_height'])) : null,
                'fontFamily' => in_array($el['font_family'] ?? null, ['Barlow', 'Barlow Condensed'], true) ? $el['font_family'] : null,
                'fontWeight' => in_array($el['font_weight'] ?? null, ['300', '400', '500', '600', '700', '800'], true) ? (int) $el['font_weight'] : null,
                'textColor' => $safeColor($el['text_color'] ?? null),
                'textAlign' => in_array($el['text_align'] ?? null, ['left', 'center', 'right', 'justify'], true) ? $el['text_align'] : null,
            ], fn ($v) => $v !== null);
        } elseif (in_array($type, SpreadElementContract::IMAGE_TYPES, true)) {
            // normalize CSS-style fit modes the model may emit; bleeding
            // image types exist to fill — letterboxing them is never right
            $fitMode = ['cover' => 'fill', 'contain' => 'fit'][$el['fit_mode'] ?? ''] ?? ($el['fit_mode'] ?? 'fill');
            if (in_array($type, ['background_image', 'fullbleed_image'], true)) {
                $fitMode = 'fill';
            }
            $content = [
                'alt' => mb_substr(strip_tags((string) $el['alt']), 0, 300),
                'fitMode' => in_array($fitMode, ['fill', 'fit', 'stretch', 'original'], true) ? $fitMode : 'fill',
                'focalPoint' => [
                    'x' => max(0, min(1, (float) ($el['focal_x'] ?? 0.5))),
                    'y' => max(0, min(1, (float) ($el['focal_y'] ?? 0.5))),
                ],
                'opacity' => (int) max(0, min(100, (float) ($el['opacity'] ?? 100))),
            ];
            $materialId = trim((string) ($el['material_id'] ?? ''));
            if ($materialId !== '') {
                // real image material → serve URL
                $asset = $this->assetForMaterial($session, $materialId);
                $content['src'] = "/api/v1/sites/{$session->site_id}/assets/{$asset}/serve";
            } else {
                // empty material_id → a fillable picture placeholder. No src; the
                // renderer draws a labelled slot and the editor lets the user drop
                // their own photo in. The alt doubles as the art-direction note.
                $content['placeholder'] = true;
            }
            if (!empty($el['caption'])) {
                $content['caption'] = mb_substr(strip_tags((string) $el['caption']), 0, 500);
            }
        } elseif (in_array($type, SpreadElementContract::SHAPE_TYPES, true)) {
            // editor-safe encoding: hex fill in content, translucency as
            // style.opacity (the editor's own semantics; renderer applies it)
            $content = ['fillColor' => $safeColor($el['fill_color']) ?? '#eeeeee'];
        } elseif ($type === 'table_frame') {
            $content = [
                'tableHeaders' => array_map(fn ($hd) => mb_substr(strip_tags((string) $hd), 0, 120), $el['table_headers']),
                'tableRows' => array_map(
                    fn ($row) => array_map(fn ($c) => mb_substr(strip_tags((string) $c), 0, 300), (array) $row),
                    array_slice($el['table_rows'], 0, 40)
                ),
                'tableStripes' => true,
            ];
        }

        $style = [];
        if (in_array($type, SpreadElementContract::SHAPE_TYPES, true)) {
            $alpha = max(0, min(100, (float) ($el['opacity'] ?? 100))) / 100;
            if ($alpha < 1) {
                $style['opacity'] = round($alpha, 2);
            }
        }

        return [
            'id' => 'is-f-' . Str::lower(Str::random(10)),
            'page_id' => $pageId,
            'spread_id' => $spreadId,
            'layer_id' => $layerId,
            'frame_type' => $frameType,
            'name' => str_replace('_', ' ', $type),
            'x' => round((float) $el['x'], 2),
            'y' => round((float) $el['y'], 2),
            'width' => round(max(1, (float) $el['w']), 2),
            'height' => round(max(1, (float) $el['h']), 2),
            'rotation' => max(0, min(360, (float) ($el['rotation'] ?? 0))),
            'z_index' => max(0, min(200, (int) ($el['z'] ?? 0))),
            'visible' => true,
            'locked' => false,
            'content' => $content,
            'style' => $style,
            'metadata' => array_filter([
                '_magType' => $type,
                '_issueStudio' => true,
                '_typography' => $typography ?: null,
            ]),
        ];
    }

    private function assetForMaterial(StudioSession $session, string $materialId): string
    {
        foreach ($session->brief['materials'] ?? [] as $m) {
            if (($m['id'] ?? null) === $materialId && !empty($m['asset_id'])) {
                return (string) $m['asset_id'];
            }
        }

        // validator guarantees resolution; belt-and-braces
        throw new \RuntimeException("Image material {$materialId} has no asset.");
    }

    private function recordReferences(StudioSession $session, StudioSpread $spread, MagazineIssue $issue): void
    {
        try {
            $edges = app(MagazineReferenceExtractor::class)->extract($issue->refresh());
            app(ReferenceRecorder::class)->persistEdges($session->site_id, 'magazine_doc', $issue->id, $edges);
            app(ReferenceRecorder::class)->persistEdges($session->site_id, 'issue_studio_spread', $spread->id, [
                ['target_type' => 'magazine_issue', 'target_id' => $issue->id, 'kind' => 'links'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('IssueStudio: reference recording failed', ['error' => $e->getMessage()]);
        }
    }
}
