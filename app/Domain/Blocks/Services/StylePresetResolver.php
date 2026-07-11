<?php

namespace App\Domain\Blocks\Services;

use App\Models\Block;
use App\Models\Site;
use App\Models\StylePreset;

/**
 * Resolves a block's effective style from its linked presets + local overrides
 * (Builder Experience P3). Resolution order, last-wins (CSS-like cascade):
 *
 *   element preset  →  option-group presets (stackable)  →  local overrides
 *
 * The block links (never copies): `block.preset_id` is the element preset, and
 * `data.__presetGroups` is an ordered list of option-group preset ids. Token
 * refs ($color.accent) are left intact here — BlockStyle's safeColor/safeDim/
 * safeCssVal compile them to var(--…) at render, so presets resolve at publish
 * to plain static CSS. Presets are cached for the lifetime of one build/resolver.
 */
class StylePresetResolver
{
    /** @var array<string, StylePreset|null> keyed "siteId:presetId" */
    private array $cache = [];

    /**
     * @param array $localStyle the block's own style (highest priority)
     * @param array $data       sanitized block data (holds __presetGroups)
     * @return array the merged style (token refs still as $-strings)
     */
    public function resolve(Block $block, Site $site, array $localStyle, array $data): array
    {
        $presetIds = [];
        if (!empty($block->preset_id)) {
            $presetIds[] = $block->preset_id;
        }
        foreach ($data['__presetGroups'] ?? [] as $gid) {
            if (is_string($gid) && $gid !== '') {
                $presetIds[] = $gid;
            }
        }
        if ($presetIds === []) {
            return $localStyle; // no preset — unchanged (zero overhead)
        }

        $merged = [];
        foreach ($presetIds as $pid) {
            $preset = $this->preset($site, $pid);
            if ($preset && is_array($preset->style)) {
                $merged = $this->deepMerge($merged, $preset->style);
            }
        }

        // local overrides win
        return $this->deepMerge($merged, $localStyle);
    }

    private function preset(Site $site, string $id): ?StylePreset
    {
        $key = $site->id . ':' . $id;
        if (!array_key_exists($key, $this->cache)) {
            // RLS scopes to the tenant; also constrain to THIS site + shared system presets.
            $this->cache[$key] = StylePreset::where('id', $id)
                ->where(fn ($w) => $w->where('site_id', $site->id)->orWhere('is_system', true))
                ->first();
        }

        return $this->cache[$key];
    }

    /** Deep-merge: nested arrays merge, scalar leaves replace (over wins). */
    private function deepMerge(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = $this->deepMerge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }
}
