<?php

namespace App\Support\Blocks;

use App\Domain\Publishing\Services\AssetPublisher;
use App\Models\Block;
use App\Models\Site;
use Illuminate\Support\Facades\File;

/**
 * Static-render helpers for the slider system: builds the runtime config blob
 * (matching the reference prototype's shape), wraps layer blocks in their
 * absolutely-positioned final-state containers, and publishes the hashed
 * motion-runtime assets next to the static output.
 *
 * Every emitted value is re-guarded here even though validation already
 * bounded it — Blade output must be safe regardless of what's in the DB.
 */
class SliderRender
{
    /** Matches SliderAnimation::COORD_PATTERN */
    private const COORD = '/^-?\d{1,4}(\.\d{1,2})?(px|%)$/';
    private const DIM = '/^\d{1,4}(px|vh|%)$/';

    public static function safeCoord(?string $v, string $fallback = '0%'): string
    {
        return (is_string($v) && preg_match(self::COORD, $v)) ? $v : $fallback;
    }

    public static function safeDim(?string $v, string $fallback): string
    {
        return (is_string($v) && preg_match(self::DIM, $v)) ? $v : $fallback;
    }

    /**
     * The runtime config blob (prototype SPEC NOTES §1): slider → slides →
     * layers with their animation scenes. Layer ids are the block UUIDs,
     * mirrored on the wrappers as data-layer-id.
     */
    public static function buildConfig(Block $sliderBlock, array $heightOverride = []): array
    {
        $data = $sliderBlock->data ?? [];
        $height = array_filter([
            'desktop' => self::safeDim($heightOverride['desktop'] ?? ($data['height']['desktop'] ?? null), '70vh'),
            'tablet' => self::safeDim($heightOverride['tablet'] ?? ($data['height']['tablet'] ?? null), '60vh'),
            'mobile' => self::safeDim($heightOverride['mobile'] ?? ($data['height']['mobile'] ?? null), '80vh'),
        ]);

        $sw = $data['swiper'] ?? [];
        $swiper = [
            'effect' => in_array($sw['effect'] ?? '', ['slide', 'fade']) ? $sw['effect'] : 'slide',
            'speed' => max(100, min(3000, (int) ($sw['speed'] ?? 700))),
            'loop' => (bool) ($sw['loop'] ?? true),
            'autoplay' => (bool) ($sw['autoplay'] ?? false),
            'autoplayDelay' => max(1000, min(30000, (int) ($sw['autoplayDelay'] ?? 6000))),
            'navigation' => (bool) ($sw['navigation'] ?? true),
            'pagination' => (bool) ($sw['pagination'] ?? true),
            'keyboard' => (bool) ($sw['keyboard'] ?? true),
            'pauseOnHover' => (bool) ($sw['pauseOnHover'] ?? true),
        ];

        $slides = [];
        foreach ($sliderBlock->children()->orderBy('order')->get() as $slide) {
            if ($slide->type !== 'slide') {
                continue;
            }
            $layers = [];
            foreach ($slide->children()->orderBy('order')->get() as $layer) {
                $layers[] = [
                    'id' => $layer->id,
                    'type' => $layer->type,
                    'animation' => self::sanitizeAnimation(($layer->data ?? [])['animation'] ?? null),
                ];
            }
            $slideData = $slide->data ?? [];
            $slides[] = [
                'id' => $slide->id,
                'duration' => isset($slideData['duration']) ? max(1000, min(30000, (int) $slideData['duration'])) : null,
                'layers' => $layers,
            ];
        }

        return [
            'id' => $sliderBlock->id,
            'height' => $height,
            'swiper' => $swiper,
            'slides' => $slides,
        ];
    }

    /**
     * Re-assert the SliderAnimation allowlists on the way OUT (defense in
     * depth: DB rows predating a rule change never reach the blob).
     */
    public static function sanitizeAnimation(?array $anim): ?array
    {
        if (!$anim) {
            return null;
        }
        $out = [];
        if (in_array($anim['split'] ?? null, SliderAnimation::SPLIT_MODES, true)) {
            $out['split'] = $anim['split'];
        }
        foreach (['in', 'loop', 'out'] as $scene) {
            if (!is_array($anim[$scene] ?? null)) {
                continue;
            }
            $s = $anim[$scene];
            $clean = [];
            if (in_array($s['preset'] ?? null, SliderAnimation::PRESETS, true)) {
                $clean['preset'] = $s['preset'];
            }
            foreach (['delay' => 10, 'duration' => 10, 'stagger' => 1] as $k => $max) {
                if (isset($s[$k]) && is_numeric($s[$k])) {
                    $clean[$k] = max(0, min($max, (float) $s[$k]));
                }
            }
            $tracks = [];
            foreach (array_slice((array) ($s['tracks'] ?? []), 0, 8) as $tr) {
                if (!is_array($tr) || !in_array($tr['attr'] ?? null, SliderAnimation::ATTRS, true)) {
                    continue;
                }
                $track = ['attr' => $tr['attr']];
                foreach (['from', 'to'] as $k) {
                    if (isset($tr[$k]) && (is_numeric($tr[$k]) || (is_string($tr[$k]) && strlen($tr[$k]) <= 60 && !preg_match('/[<>"\']/', $tr[$k])))) {
                        $track[$k] = $tr[$k];
                    }
                }
                foreach (['delay' => 10, 'duration' => 10] as $k => $max) {
                    if (isset($tr[$k]) && is_numeric($tr[$k])) {
                        $track[$k] = max(0, min($max, (float) $tr[$k]));
                    }
                }
                if (isset($tr['ease']) && is_string($tr['ease']) && preg_match(SliderAnimation::EASE_PATTERN, $tr['ease'])) {
                    $track['ease'] = $tr['ease'];
                }
                if (isset($tr['yoyo'])) {
                    $track['yoyo'] = (bool) $tr['yoyo'];
                }
                if (isset($tr['repeat']) && is_numeric($tr['repeat'])) {
                    $track['repeat'] = max(-1, min(20, (int) $tr['repeat']));
                }
                $tracks[] = $track;
            }
            if ($tracks !== []) {
                $clean['tracks'] = $tracks;
            }
            if ($clean !== []) {
                $out[$scene] = $clean;
            }
        }
        $trig = $anim['trigger'] ?? null;
        if (is_array($trig) && in_array($trig['action'] ?? null, SliderAnimation::TRIGGER_ACTIONS, true)) {
            $target = $trig['target'] ?? '';
            if (is_string($target) && strlen($target) <= 300 && !preg_match('/^(javascript|data|vbscript):/i', trim($target))) {
                $out['trigger'] = ['action' => $trig['action'], 'target' => $target];
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * Wrap a rendered layer block in its absolutely-positioned container.
     * FINAL state is authored here (CSS); the runtime applies from-states only
     * when a timeline plays — this is what makes no-JS/reduced-motion correct.
     */
    public static function wrapLayer(Block $layer, string $innerHtml): string
    {
        $layout = ($layer->data ?? [])['layout'] ?? [];
        $style = 'left:' . self::safeCoord($layout['x'] ?? null, '0%')
            . ';top:' . self::safeCoord($layout['y'] ?? null, '0%');
        if (isset($layout['widthPct']) && is_numeric($layout['widthPct'])) {
            $style .= ';width:' . max(0, min(100, (float) $layout['widthPct'])) . '%';
        }
        if (isset($layout['heightPct']) && is_numeric($layout['heightPct'])) {
            $style .= ';height:' . max(0, min(100, (float) $layout['heightPct'])) . '%';
        }
        $z = max(0, min(99, (int) ($layout['zIndex'] ?? 2)));
        $style .= ';z-index:' . $z;
        if (isset($layout['rotation']) && is_numeric($layout['rotation']) && (float) $layout['rotation'] !== 0.0) {
            $style .= ';transform:rotate(' . max(-360, min(360, (float) $layout['rotation'])) . 'deg)';
        }

        $animated = !empty(($layer->data ?? [])['animation']) ? ' data-animated' : '';

        // Per-breakpoint overrides: scoped <style> with the SAME breakpoints
        // as BlockStyle's responsive emitters (tablet ≤1023, mobile ≤767)
        $respCss = '';
        $scope = '';
        $respLayout = ($layer->data ?? [])['responsiveLayout'] ?? null;
        if (is_array($respLayout)) {
            $scopeClass = 'spl-' . substr(md5($layer->id), 0, 8);
            foreach (['tablet' => 1023, 'mobile' => 767] as $bp => $maxWidth) {
                $o = $respLayout[$bp] ?? null;
                if (!is_array($o)) {
                    continue;
                }
                $rules = [];
                if (isset($o['x'])) $rules[] = 'left:' . self::safeCoord($o['x'], '0%') . ' !important';
                if (isset($o['y'])) $rules[] = 'top:' . self::safeCoord($o['y'], '0%') . ' !important';
                if (isset($o['widthPct']) && is_numeric($o['widthPct'])) $rules[] = 'width:' . max(0, min(100, (float) $o['widthPct'])) . '% !important';
                if (isset($o['heightPct']) && is_numeric($o['heightPct'])) $rules[] = 'height:' . max(0, min(100, (float) $o['heightPct'])) . '% !important';
                if (!empty($o['hidden'])) $rules[] = 'display:none !important';
                if ($rules !== []) {
                    $respCss .= "@media (max-width:{$maxWidth}px){.{$scopeClass}{" . implode(';', $rules) . ";}}";
                }
            }
            if ($respCss !== '') {
                $scope = ' ' . $scopeClass;
            }
        }

        return ($respCss !== '' ? '<style>' . $respCss . '</style>' : '')
            . '<div class="sp-layer' . $scope . '" data-layer-id="' . e($layer->id) . '"' . $animated
            . ' style="' . e($style) . '">' . $innerHtml . '</div>';
    }

    /**
     * Copy the hashed motion-runtime assets into the deploy target (same
     * convention as site-cursor/experience runtime). Returns the public URLs.
     *
     * @return array{js: string, css: string}
     */
    public static function publishRuntime(Site $site): array
    {
        $jsSource = resource_path('js/motion-runtime.js');
        $cssSource = resource_path('js/motion-runtime.css');
        $jsHash = substr(md5_file($jsSource), 0, 8);
        $cssHash = substr(md5_file($cssSource), 0, 8);

        if ($site->custom_domain) {
            $tenantBase = config('publishing.tenant_base', '/home/cytechno/web');
            $safeDomain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $site->custom_domain);
            $target = $tenantBase . '/' . $safeDomain . '/public_html';
        } else {
            $target = config('publishing.public_path') . '/' . $site->slug;
        }

        try {
            File::ensureDirectoryExists("{$target}/assets");
            foreach ([["motion-runtime.{$jsHash}.js", $jsSource], ["motion-runtime.{$cssHash}.css", $cssSource]] as [$name, $source]) {
                if (!file_exists("{$target}/assets/{$name}")) {
                    File::copy($source, "{$target}/assets/{$name}");
                    @chmod("{$target}/assets/{$name}", 0664);
                }
            }
        } catch (\Throwable $e) {
            logger()->warning("motion-runtime publish failed for site {$site->id}: {$e->getMessage()}");
        }

        return [
            'js' => "/assets/motion-runtime.{$jsHash}.js",
            'css' => "/assets/motion-runtime.{$cssHash}.css",
        ];
    }

    /**
     * Resolve a slide background asset to its static URL (copies the file to
     * the deploy target via the existing AssetPublisher).
     */
    public static function resolveBackgroundUrl(array $background): ?string
    {
        if (!empty($background['assetId'])) {
            $url = AssetPublisher::resolveUrl($background['assetId']);
            if ($url) {
                return $url;
            }
        }
        $src = $background['src'] ?? null;

        return (is_string($src) && $src !== '' && !preg_match('/^(javascript|data|vbscript):/i', trim($src))) ? $src : null;
    }
}
