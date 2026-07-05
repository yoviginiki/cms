<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineAssetReference;
use App\Domain\Magazine\Models\MagazineDocVersion;
use App\Domain\Magazine\Models\MagazineDtpPage;
use App\Domain\Magazine\Models\MagazineFrame;
use App\Domain\Magazine\Models\MagazineLayer;
use App\Domain\Magazine\Models\MagazineSpread;
use Illuminate\Support\Facades\DB;

class DtpDocumentService
{
    private const VERSION_CAP = 20;

    /** snapshot current persisted doc into the version trail (cap per issue) */
    public function snapshotVersion(MagazineIssue $issue, ?string $label = null): ?MagazineDocVersion
    {
        $doc = $this->loadDocument($issue);
        if (($doc['meta']['frame_count'] ?? 0) === 0) {
            return null; // nothing persisted yet — empty snapshots are noise
        }
        $version = MagazineDocVersion::create([
            'issue_id' => $issue->id,
            'label' => $label,
            'document' => $doc,
            'page_count' => $doc['meta']['page_count'] ?? 0,
            'frame_count' => $doc['meta']['frame_count'] ?? 0,
            'created_at' => now(),
        ]);
        $stale = MagazineDocVersion::where('issue_id', $issue->id)
            ->orderByDesc('created_at')
            ->skip(self::VERSION_CAP)
            ->take(100)
            ->pluck('id');
        if ($stale->isNotEmpty()) {
            MagazineDocVersion::whereIn('id', $stale)->delete();
        }

        return $version;
    }

    /**
     * Load full DTP document for an issue.
     */
    public function loadDocument(MagazineIssue $issue): array
    {
        $spreads = MagazineSpread::where('issue_id', $issue->id)->orderBy('spread_index')->get();
        $pages = MagazineDtpPage::where('issue_id', $issue->id)->orderBy('page_index')->get();
        $layers = MagazineLayer::where('issue_id', $issue->id)->orderBy('layer_order')->get();
        $frames = MagazineFrame::where('issue_id', $issue->id)->orderBy('z_index')->get();
        $assetRefs = MagazineAssetReference::where('issue_id', $issue->id)->get();

        return [
            'issue' => [
                'id' => $issue->id,
                'title' => $issue->title,
                'subtitle' => $issue->subtitle,
                'status' => $issue->status,
                'updated_at' => $issue->updated_at?->toIso8601String(),
            ],
            'spreads' => $spreads->map(fn ($s) => [
                'id' => $s->id,
                'spread_index' => $s->spread_index,
                'name' => $s->name,
                'metadata' => $s->metadata,
            ])->values(),
            'pages' => $pages->map(fn ($p) => [
                'id' => $p->id,
                'spread_id' => $p->spread_id,
                'page_index' => $p->page_index,
                'side' => $p->side,
                'width' => $p->width,
                'height' => $p->height,
                'bleed' => $p->bleed,
                'margins' => $p->margins,
                'safe_area' => $p->safe_area,
                'background' => $p->background,
                'master_page_id' => $p->master_page_id,
                'metadata' => $p->metadata,
            ])->values(),
            'layers' => $layers->map(fn ($l) => [
                'id' => $l->id,
                'page_id' => $l->page_id,
                'name' => $l->name,
                'layer_order' => $l->layer_order,
                'visible' => $l->visible,
                'locked' => $l->locked,
                'metadata' => $l->metadata,
            ])->values(),
            'frames' => $frames->map(fn ($f) => [
                'id' => $f->id,
                'page_id' => $f->page_id,
                'spread_id' => $f->spread_id,
                'layer_id' => $f->layer_id,
                'frame_type' => $f->frame_type->value ?? $f->frame_type,
                'name' => $f->name,
                'x' => $f->x,
                'y' => $f->y,
                'width' => $f->width,
                'height' => $f->height,
                'rotation' => $f->rotation,
                'z_index' => $f->z_index,
                'visible' => $f->visible,
                'locked' => $f->locked,
                'content' => $f->content,
                'style' => $f->style,
                'metadata' => $f->metadata,
            ])->values(),
            'asset_references' => $assetRefs->map(fn ($a) => [
                'id' => $a->id,
                'frame_id' => $a->frame_id,
                'source_url' => $a->source_url,
                'alt' => $a->alt,
                'caption' => $a->caption,
                'metadata' => $a->metadata,
            ])->values(),
            'meta' => [
                'content_hash' => hash('sha256', json_encode([
                    $spreads->pluck('id'),
                    $pages->pluck('id'),
                    $frames->pluck('id'),
                ])),
                'spread_count' => $spreads->count(),
                'page_count' => $pages->count(),
                'frame_count' => $frames->count(),
                'issueSettings' => $issue->layout_final['issueSettings'] ?? null,
                'viewerSettings' => $issue->layout_final['viewerSettings'] ?? null,
                'styles' => $issue->layout_final['styles'] ?? [],
                'masterPages' => $issue->layout_final['masterPages'] ?? [],
            ],
        ];
    }

    /**
     * Save full DTP document (atomic replace).
     */
    public function saveDocument(MagazineIssue $issue, array $data): array
    {
        return DB::transaction(function () use ($issue, $data) {
            $issueId = $issue->id;

            // Persist issue settings + viewer settings
            $meta = $data['meta'] ?? [];
            $layoutFinal = $issue->layout_final ?? [];
            $changed = false;
            if (!empty($meta['issueSettings'])) {
                $allowed = ['layoutMode', 'coverMode', 'readingDirection'];
                $layoutFinal['issueSettings'] = array_intersect_key((array) $meta['issueSettings'], array_flip($allowed));
                $changed = true;
            }
            if (!empty($meta['viewerSettings'])) {
                $allowed = ['display_mode', 'bg_color', 'ui_theme', 'arrow_color', 'page_transition', 'transition_speed', 'show_thumbnails', 'show_page_numbers', 'auto_hide_ui', 'side_banners', 'audio'];
                $layoutFinal['viewerSettings'] = array_intersect_key((array) $meta['viewerSettings'], array_flip($allowed));
                $changed = true;
            }
            // Paragraph/character styles + master pages (audit W0-6: these were
            // silently dropped here — the editor round-tripped them for nothing)
            if (array_key_exists('styles', $meta)) {
                $layoutFinal['styles'] = array_slice((array) $meta['styles'], 0, 200);
                $changed = true;
            }
            if (array_key_exists('masterPages', $meta)) {
                $layoutFinal['masterPages'] = array_slice((array) $meta['masterPages'], 0, 20);
                $changed = true;
            }
            if ($changed) {
                $issue->update(['layout_final' => $layoutFinal]);
            }

            // Version trail (W3): snapshot the CURRENT persisted document
            // before the atomic replace — every save becomes restorable.
            $this->snapshotVersion($issue);

            // Delete existing DTP data for this issue
            MagazineAssetReference::where('issue_id', $issueId)->delete();
            MagazineFrame::where('issue_id', $issueId)->delete();
            MagazineLayer::where('issue_id', $issueId)->delete();
            MagazineDtpPage::where('issue_id', $issueId)->delete();
            MagazineSpread::where('issue_id', $issueId)->delete();

            // ID mapping for client-generated IDs → server UUIDs
            $spreadMap = [];
            $pageMap = [];
            $layerMap = [];
            $frameMap = [];

            // Insert spreads
            foreach ($data['spreads'] ?? [] as $s) {
                $spread = MagazineSpread::create([
                    'issue_id' => $issueId,
                    'spread_index' => $s['spread_index'],
                    'name' => $s['name'] ?? null,
                    'metadata' => $s['metadata'] ?? null,
                ]);
                $spreadMap[$s['id'] ?? ''] = $spread->id;
            }

            // Insert pages
            foreach ($data['pages'] ?? [] as $p) {
                $page = MagazineDtpPage::create([
                    'issue_id' => $issueId,
                    'spread_id' => $spreadMap[$p['spread_id'] ?? ''] ?? null,
                    'page_index' => $p['page_index'],
                    'side' => $p['side'] ?? 'single',
                    'width' => $p['width'] ?? 595,
                    'height' => $p['height'] ?? 842,
                    'bleed' => $p['bleed'] ?? null,
                    'margins' => $p['margins'] ?? null,
                    'safe_area' => $p['safe_area'] ?? null,
                    'background' => $p['background'] ?? null,
                    'master_page_id' => $p['master_page_id'] ?? null,
                    'metadata' => $p['metadata'] ?? null,
                ]);
                $pageMap[$p['id'] ?? ''] = $page->id;
            }

            // Insert layers
            foreach ($data['layers'] ?? [] as $l) {
                $layer = MagazineLayer::create([
                    'issue_id' => $issueId,
                    'page_id' => $pageMap[$l['page_id'] ?? ''] ?? null,
                    'name' => $l['name'],
                    'layer_order' => $l['layer_order'] ?? 0,
                    'visible' => $l['visible'] ?? true,
                    'locked' => $l['locked'] ?? false,
                    'metadata' => $l['metadata'] ?? null,
                ]);
                $layerMap[$l['id'] ?? ''] = $layer->id;
            }

            // Insert frames
            foreach ($data['frames'] ?? [] as $f) {
                $frame = MagazineFrame::create([
                    'issue_id' => $issueId,
                    'spread_id' => $spreadMap[$f['spread_id'] ?? ''] ?? null,
                    'page_id' => $pageMap[$f['page_id'] ?? ''] ?? null,
                    'layer_id' => $layerMap[$f['layer_id'] ?? ''] ?? null,
                    'frame_type' => $f['frame_type'],
                    'name' => $f['name'] ?? null,
                    'x' => $f['x'],
                    'y' => $f['y'],
                    'width' => $f['width'],
                    'height' => $f['height'],
                    'rotation' => $f['rotation'] ?? 0,
                    'z_index' => $f['z_index'] ?? 0,
                    'visible' => $f['visible'] ?? true,
                    'locked' => $f['locked'] ?? false,
                    'content' => $f['content'] ?? null,
                    'style' => $f['style'] ?? null,
                    'metadata' => $f['metadata'] ?? null,
                ]);
                $frameMap[$f['id'] ?? ''] = $frame->id;
            }

            // Insert asset references
            foreach ($data['asset_references'] ?? [] as $a) {
                MagazineAssetReference::create([
                    'issue_id' => $issueId,
                    'frame_id' => $frameMap[$a['frame_id'] ?? ''] ?? null,
                    'source_url' => $a['source_url'] ?? null,
                    'alt' => $a['alt'] ?? null,
                    'caption' => $a['caption'] ?? null,
                    'metadata' => $a['metadata'] ?? null,
                ]);
            }

            return $this->loadDocument($issue);
        });
    }
}
