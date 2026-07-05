<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineFrame;

/**
 * Wires DTP magazines into the entity_references dependency graph (W3 —
 * audit's "biggest remaining architectural hole"): every asset a magazine
 * uses becomes an edge (magazine_doc → asset), which gives magazines the
 * SAME delete-protection and staleness behavior pages/posts already have.
 *
 * Scanned surfaces: frame content (image src, video poster, audio url,
 * inline <img> in text slices), viewer settings (side banners, audio
 * tracks) and persisted master pages.
 */
class MagazineReferenceExtractor
{
    /** @return list<array{target_type:string,target_id:string,kind:string}> */
    public function extract(MagazineIssue $issue): array
    {
        $assetIds = [];

        $harvest = function (?string $value) use (&$assetIds): void {
            foreach ($this->assetIdsFromString((string) $value) as $id) {
                $assetIds[$id] = true;
            }
        };

        $frames = MagazineFrame::where('issue_id', $issue->id)->get(['content', 'metadata']);
        foreach ($frames as $frame) {
            $c = is_array($frame->content) ? $frame->content : [];
            $harvest($c['src'] ?? null);
            $harvest($c['html'] ?? null);      // inline <img> in text slices
            $harvest($c['videoUrl'] ?? null);
            $harvest($c['audioUrl'] ?? null);
            if (!empty($c['posterAssetId']) && $this->isUuid($c['posterAssetId'])) {
                $assetIds[$c['posterAssetId']] = true;
            }
        }

        $vs = $issue->layout_final['viewerSettings'] ?? [];
        foreach (($vs['side_banners'] ?? []) as $b) {
            $harvest(is_array($b) ? ($b['src'] ?? null) : null);
        }
        foreach (($vs['audio']['tracks'] ?? []) as $t) {
            $harvest(is_array($t) ? ($t['src'] ?? null) : null);
        }
        foreach (($issue->layout_final['masterPages'] ?? []) as $mp) {
            foreach (($mp['elements'] ?? []) as $el) {
                $harvest(is_array($el['data'] ?? null) ? ($el['data']['src'] ?? null) : null);
            }
        }

        return array_map(
            fn (string $id) => ['target_type' => 'asset', 'target_id' => $id, 'kind' => 'uses_asset'],
            array_keys($assetIds),
        );
    }

    /** asset UUIDs from serve/media URLs embedded anywhere in a string */
    private function assetIdsFromString(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $ids = [];
        // /api/v1/sites/{site}/assets/{asset}/serve  and  /media/{site}/{asset}
        if (preg_match_all('#/api/v1/sites/[0-9a-f\-]{36}/assets/([0-9a-f\-]{36})/serve#i', $value, $m)) {
            array_push($ids, ...$m[1]);
        }
        if (preg_match_all('#/media/[0-9a-f\-]{36}/([0-9a-f\-]{36})#i', $value, $m)) {
            array_push($ids, ...$m[1]);
        }

        return $ids;
    }

    private function isUuid(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[0-9a-f\-]{36}$/i', $v) === 1;
    }
}
