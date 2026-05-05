<?php

namespace App\Domain\Publishing\Services;

class BlockStyleResolver
{
    /**
     * Generate inline style string from block style props.
     */
    public function resolveInlineStyle(array $style, string $editorMode = 'block'): string
    {
        $css = [];

        // Typography
        if (!empty($style['typography'])) {
            $t = $style['typography'];
            if (!empty($t['fontFamily'])) $css[] = "font-family: {$t['fontFamily']}";
            if (!empty($t['fontSize'])) $css[] = "font-size: {$t['fontSize']}";
            if (!empty($t['fontWeight'])) $css[] = "font-weight: {$t['fontWeight']}";
            if (!empty($t['lineHeight'])) $css[] = "line-height: {$t['lineHeight']}";
            if (!empty($t['letterSpacing'])) $css[] = "letter-spacing: {$t['letterSpacing']}";
            if (!empty($t['textAlign'])) $css[] = "text-align: {$t['textAlign']}";
            if (!empty($t['textTransform'])) $css[] = "text-transform: {$t['textTransform']}";
            if (!empty($t['textColor'])) $css[] = "color: {$t['textColor']}";
            if (!empty($t['paragraphSpacingAfter'])) $css[] = "margin-bottom: {$t['paragraphSpacingAfter']}";
        }

        // Spacing
        if (!empty($style['spacing'])) {
            $s = $style['spacing'];
            foreach (['marginTop', 'marginRight', 'marginBottom', 'marginLeft'] as $prop) {
                if (!empty($s[$prop])) $css[] = $this->camelToKebab($prop) . ": {$s[$prop]}";
            }
            foreach (['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft'] as $prop) {
                if (!empty($s[$prop])) $css[] = $this->camelToKebab($prop) . ": {$s[$prop]}";
            }
            if (!empty($s['gap'])) $css[] = "gap: {$s['gap']}";
        }

        // Visual
        if (!empty($style['visual'])) {
            $v = $style['visual'];
            if (!empty($v['backgroundColor'])) $css[] = "background-color: {$v['backgroundColor']}";
            if (!empty($v['backgroundGradient'])) $css[] = "background: {$v['backgroundGradient']}";
            if (!empty($v['backgroundImage'])) $css[] = "background-image: url('{$v['backgroundImage']}'); background-size: cover; background-position: center";
            if (!empty($v['borderWidth']) && !empty($v['borderColor'])) {
                $bs = $v['borderStyle'] ?? 'solid';
                $css[] = "border: {$v['borderWidth']} {$bs} {$v['borderColor']}";
            }
            if (!empty($v['borderRadius'])) $css[] = "border-radius: {$v['borderRadius']}";
            if (!empty($v['boxShadow']) && $v['boxShadow'] !== 'none') {
                $shadows = ['sm' => '0 1px 2px rgba(0,0,0,0.04)', 'md' => '0 4px 12px rgba(0,0,0,0.06)', 'lg' => '0 12px 32px rgba(0,0,0,0.10)'];
                $css[] = "box-shadow: " . ($shadows[$v['boxShadow']] ?? $v['boxShadow']);
            }
            if (isset($v['opacity']) && $v['opacity'] < 1) $css[] = "opacity: {$v['opacity']}";
            if (!empty($v['overflow']) && $v['overflow'] !== 'visible') $css[] = "overflow: {$v['overflow']}";
        }

        // Layout
        if (!empty($style['layout'])) {
            $l = $style['layout'];

            if ($editorMode === 'magazine' && ($l['position'] ?? '') === 'absolute') {
                $css[] = "position: absolute";
                if (isset($l['x'])) $css[] = "left: {$l['x']}px";
                if (isset($l['y'])) $css[] = "top: {$l['y']}px";
            }

            if (!empty($l['width'])) $css[] = "width: {$l['width']}";
            if (!empty($l['maxWidth'])) $css[] = "max-width: {$l['maxWidth']}";
            if (!empty($l['minHeight'])) $css[] = "min-height: {$l['minHeight']}";
            if (!empty($l['display']) && $l['display'] !== 'block') $css[] = "display: {$l['display']}";
            if (!empty($l['flexDirection'])) $css[] = "flex-direction: {$l['flexDirection']}";
            if (!empty($l['justifyContent'])) $css[] = "justify-content: {$l['justifyContent']}";
            if (!empty($l['alignItems'])) $css[] = "align-items: {$l['alignItems']}";
            if (isset($l['zIndex'])) $css[] = "z-index: {$l['zIndex']}";
            if (!empty($l['rotation'])) $css[] = "transform: rotate({$l['rotation']}deg)";

            if (!empty($l['alignment']) && $l['alignment'] !== 'stretch') {
                $alignMap = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
                if (isset($alignMap[$l['alignment']])) $css[] = "margin: {$alignMap[$l['alignment']]}";
            }
        }

        return implode('; ', $css);
    }

    /**
     * Generate CSS class string from block style props.
     */
    public function resolveClasses(array $style, ?array $advanced = null): string
    {
        $classes = [];

        if (!empty($advanced['customClass'])) {
            $classes[] = e($advanced['customClass']);
        }

        return implode(' ', $classes);
    }

    /**
     * Generate wrapper CSS for magazine-mode pages.
     */
    public function generateMagazinePageCss(int $canvasWidth, int $canvasHeight): string
    {
        $ratio = $canvasWidth / $canvasHeight;

        return <<<CSS
.magazine-page {
  position: relative;
  width: 100%;
  max-width: {$canvasWidth}px;
  aspect-ratio: {$ratio};
  margin: 0 auto;
  overflow: hidden;
}
.magazine-page > * {
  position: absolute;
}
@media (max-width: {$canvasWidth}px) {
  .magazine-page {
    max-width: 100vw;
  }
}
CSS;
    }

    /**
     * Generate animation CSS for a block.
     */
    public function resolveAnimationStyle(array $animation): string
    {
        if (empty($animation['entrance']) || $animation['entrance'] === 'none') return '';

        $duration = $animation['duration'] ?? 400;
        $delay = $animation['delay'] ?? 0;

        return "animation-duration: {$duration}ms; animation-delay: {$delay}ms; animation-fill-mode: both;";
    }

    /**
     * Check if block should be hidden on current device.
     */
    public function isHiddenOnDevice(array $responsive, string $device): bool
    {
        return in_array($device, $responsive['hideOn'] ?? []);
    }

    private function camelToKebab(string $str): string
    {
        return strtolower(preg_replace('/[A-Z]/', '-$0', $str));
    }
}
