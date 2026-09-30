<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Models\Site;

/**
 * The published typography, scoped to the canvas editor surface: the SAME
 * design-token CSS the build emits (DesignTokenGenerator — fonts, @font-face,
 * sizes, line-heights, site overrides) with :root/html/body re-targeted to the
 * scope class, plus the body/heading rules of BuildPageService::buildCriticalCss
 * and the browser-default heading/paragraph metrics the admin's CSS reset
 * strips. Without it the canvas shows the admin font (Inter, 16px).
 */
class CanvasTypographyCss
{
    public function __construct(private DesignTokenGenerator $tokens) {}

    public function forSite(Site $site, string $scope = '.cv-typo'): string
    {
        $css = $this->tokens->generate($site);

        // @import must lead the stylesheet or the browser drops it
        preg_match_all('/@import[^;]+;/', $css, $m);
        $css = preg_replace('/@import[^;]+;\s*/', '', $css);

        // page-level decoration (e.g. a fixed body::after texture overlay) would
        // cover the whole admin — drop it; the canvas only needs typography
        $css = preg_replace('/(^|\})\s*(?:html|body)::?[a-z-]+[^{]*\{[^}]*\}/m', '$1', $css);

        // :root / html / body selectors → the canvas scope (vars stay off the admin UI)
        $css = preg_replace('/(^|[}\s,])(?::root|html|body)(?=\s*[{,])/m', '$1' . $scope, $css);

        return implode("\n", $m[0]) . "\n" . $css . "\n" . self::baseRules($scope);
    }

    /** Mirrors buildCriticalCss (body, a, h1-h6) + UA metrics the admin reset removes. */
    public static function baseRules(string $s): string
    {
        return "{$s}{font-family:var(--font-body,system-ui,-apple-system,sans-serif);font-size:var(--font-size-base,1rem);line-height:var(--line-height-body,1.6);letter-spacing:var(--letter-spacing-body,0);color:var(--color-text,#1e293b)}\n"
            . "{$s} a{color:var(--color-link,var(--color-primary,#3b82f6));text-decoration:var(--text-decoration-link,none)}\n"
            . "{$s} :is(h1,h2,h3,h4,h5,h6){font-family:var(--font-heading,inherit);font-weight:var(--heading-weight,700);letter-spacing:var(--letter-spacing-heading,0);line-height:var(--line-height-heading,1.25);color:var(--color-heading,var(--color-text,#0f172a))}\n"
            . "{$s} h1{font-size:2em;margin:.67em 0}{$s} h2{font-size:1.5em;margin:.83em 0}{$s} h3{font-size:1.17em;margin:1em 0}"
            . "{$s} h4{font-size:1em;margin:1.33em 0}{$s} h5{font-size:.83em;margin:1.67em 0}{$s} h6{font-size:.67em;margin:2.33em 0}\n"
            . "{$s} p{margin:1em 0}{$s} :is(ul,ol){margin:1em 0;padding-left:40px}{$s} ul{list-style:disc}{$s} ol{list-style:decimal}\n"
            . "{$s} blockquote{margin:1em 40px}{$s} strong,{$s} b{font-weight:bolder}{$s} em,{$s} i{font-style:italic}\n"
            // Text blocks are `.prose`. The admin loads Tailwind Typography, which
            // turns that into 16px / line-height 1.75 / its own margins, colours
            // and quote/list chrome; the published site only has the few rules
            // below (BuildPageService critical CSS). Undo the plugin, apply those.
            . "{$s} .prose{font-size:inherit;line-height:inherit;color:inherit;max-width:none}\n"
            . "{$s} .prose p{margin:0 0 1em}{$s} .prose :is(h2,h3,h4){margin:1.5em 0 .5em}{$s} .prose :is(ul,ol){padding-left:1.5em}\n"
            . "{$s} .prose li{margin:0;padding:0}{$s} .prose li::marker{color:inherit}\n"
            . "{$s} .prose :is(strong,b){color:inherit;font-weight:bolder}{$s} .prose :is(em,i){color:inherit}\n"
            . "{$s} .prose blockquote{border:0;padding:0;font-style:normal;font-weight:inherit;color:inherit;quotes:none}"
            . "{$s} .prose blockquote p::before,{$s} .prose blockquote p::after{content:none}\n"
            . "{$s} .prose :is(img,figure,hr){margin:0}{$s} .prose code{font-weight:inherit;color:inherit}{$s} .prose code::before,{$s} .prose code::after{content:none}\n";
    }
}
