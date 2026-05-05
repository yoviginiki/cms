<?php

namespace App\Domain\Grid\Services;

use App\Models\Grid;

class GridCssGenerator
{
    public function generate(Grid $grid): string
    {
        $css = '';

        // ─── Grid wrapper (full-bleed support) ───
        if ($grid->full_bleed) {
            $css .= ".site-grid-wrap {\n";
            $css .= "  width: 100%;\n";
            if ($grid->background_json) {
                $css .= $this->backgroundCss($grid->background_json, '  ');
            }
            if ($grid->min_height) {
                $css .= "  min-height: {$grid->min_height};\n";
            }
            $css .= "}\n";
        }

        // ─── Main grid container ───
        $css .= ".site-grid {\n";
        $css .= "  display: grid;\n";
        $css .= "  grid-template-columns: {$grid->col_tracks};\n";
        $css .= "  grid-template-rows: {$grid->row_tracks};\n";
        $css .= "  grid-template-areas:\n    {$grid->areas};\n";
        $css .= "  gap: {$grid->gap_y} {$grid->gap_x};\n";
        $css .= "  max-width: {$grid->container_width};\n";
        $css .= "  margin: 0 auto;\n";
        $css .= "  width: 100%;\n";

        if ($grid->container_padding && $grid->container_padding !== '0') {
            $css .= "  padding: {$grid->container_padding};\n";
        }
        if (!$grid->full_bleed) {
            if ($grid->min_height) {
                $css .= "  min-height: {$grid->min_height};\n";
            }
            if ($grid->background_json) {
                $css .= $this->backgroundCss($grid->background_json, '  ');
            }
        }
        if ($grid->align_items && $grid->align_items !== 'stretch') {
            $css .= "  align-items: {$grid->align_items};\n";
        }
        if ($grid->justify_items && $grid->justify_items !== 'stretch') {
            $css .= "  justify-items: {$grid->justify_items};\n";
        }

        // Layout modes
        if ($grid->layout_mode === 'horizontal-scroll') {
            $css .= "  grid-auto-flow: column;\n";
            $css .= "  overflow-x: auto;\n";
            $css .= "  scroll-snap-type: x mandatory;\n";
            $css .= "  -webkit-overflow-scrolling: touch;\n";
        } elseif ($grid->layout_mode === 'snap-sections') {
            $css .= "  overflow-y: auto;\n";
            $css .= "  scroll-snap-type: y mandatory;\n";
            $css .= "  height: 100vh;\n";
        } elseif ($grid->overflow_x && $grid->overflow_x !== 'visible') {
            $css .= "  overflow-x: {$grid->overflow_x};\n";
        }

        $css .= "}\n";

        // Horizontal scroll / snap: each direct child is a snap target
        if (in_array($grid->layout_mode, ['horizontal-scroll', 'snap-sections'])) {
            $axis = $grid->layout_mode === 'horizontal-scroll' ? 'x' : 'y';
            $dim = $axis === 'x' ? 'width' : 'height';
            $css .= ".site-grid > * {\n";
            $css .= "  scroll-snap-align: start;\n";
            if ($grid->layout_mode === 'horizontal-scroll') {
                $css .= "  min-width: 100%;\n";
            } elseif ($grid->layout_mode === 'snap-sections') {
                $css .= "  min-height: 100vh;\n";
            }
            $css .= "}\n";
        }

        // ─── Position area classes ───
        foreach ($grid->positions as $pos) {
            $extra = $pos->css_class ? " {$pos->css_class}" : '';
            $sel = ".pos-{$pos->area_name}";

            $props = "grid-area: {$pos->area_name};";

            if ($pos->min_height) {
                $props .= " min-height: {$pos->min_height};";
            }
            if ($pos->max_width) {
                $props .= " max-width: {$pos->max_width};";
            }
            if ($pos->align_self) {
                $props .= " align-self: {$pos->align_self};";
            }
            if ($pos->justify_self) {
                $props .= " justify-self: {$pos->justify_self};";
            }
            if ($pos->overflow) {
                $props .= " overflow: {$pos->overflow};";
            }

            // Full-bleed position (breaks out of grid container to full viewport width)
            if ($pos->full_bleed) {
                $props .= " width: 100vw; margin-left: calc(-50vw + 50%);";
            }

            // Background
            if ($pos->background_json) {
                $bg = $pos->background_json;
                if (!empty($bg['color'])) $props .= " background-color: {$bg['color']};";
                if (!empty($bg['image'])) $props .= " background-image: url('{$bg['image']}'); background-size: cover; background-position: center;";
                if (!empty($bg['gradient'])) $props .= " background: {$bg['gradient']};";
                if (!empty($bg['overlay'])) $props .= " position: relative;";
            }

            // Padding
            if ($pos->padding_json) {
                $pad = $pos->padding_json;
                $top = $pad['top'] ?? '0';
                $right = $pad['right'] ?? '0';
                $bottom = $pad['bottom'] ?? '0';
                $left = $pad['left'] ?? '0';
                $props .= " padding: {$top} {$right} {$bottom} {$left};";
            }

            // Border
            if ($pos->border_json) {
                $b = $pos->border_json;
                if (!empty($b['width']) && !empty($b['color'])) {
                    $style = $b['style'] ?? 'solid';
                    $props .= " border: {$b['width']} {$style} {$b['color']};";
                }
                if (!empty($b['radius'])) {
                    $props .= " border-radius: {$b['radius']};";
                }
            }

            // Shadow
            if ($pos->shadow) {
                $props .= " box-shadow: {$pos->shadow};";
            }

            $css .= "{$sel} { {$props} }\n";

            // Background overlay pseudo-element
            if ($pos->background_json && !empty($pos->background_json['overlay'])) {
                $ov = $pos->background_json['overlay'];
                $css .= "{$sel}::before { content: ''; position: absolute; inset: 0; background: {$ov}; pointer-events: none; z-index: 0; }\n";
                $css .= "{$sel} > * { position: relative; z-index: 1; }\n";
            }
        }

        // ─── Responsive breakpoints ───
        $breakpoints = $grid->breakpoints_json ?? [];

        if (!empty($breakpoints['tablet'])) {
            $css .= $this->generateBreakpoint($breakpoints['tablet'], $grid, 1024);
        }

        if (!empty($breakpoints['mobile'])) {
            $css .= $this->generateBreakpoint($breakpoints['mobile'], $grid, 768);
        }

        // Mobile order fallback
        $mobileOrders = [];
        foreach ($grid->positions as $pos) {
            if ($pos->mobile_order > 0) {
                $mobileOrders[] = ".pos-{$pos->area_name} { order: {$pos->mobile_order}; }";
            }
        }

        if (!empty($mobileOrders) && empty($breakpoints['mobile'])) {
            $css .= "@media (max-width: 768px) {\n";
            $css .= "  .site-grid { grid-template-columns: 1fr; }\n";
            foreach ($mobileOrders as $order) {
                $css .= "  {$order}\n";
            }
            $css .= "}\n";
        }

        return $css;
    }

    private function backgroundCss(array $bg, string $indent = ''): string
    {
        $css = '';
        if (!empty($bg['color'])) {
            $css .= "{$indent}background-color: {$bg['color']};\n";
        }
        if (!empty($bg['gradient'])) {
            $css .= "{$indent}background: {$bg['gradient']};\n";
        }
        if (!empty($bg['image'])) {
            $css .= "{$indent}background-image: url('{$bg['image']}');\n";
            $css .= "{$indent}background-size: cover;\n";
            $css .= "{$indent}background-position: center;\n";
        }
        return $css;
    }

    private function generateBreakpoint(array $bp, Grid $grid, int $maxWidth): string
    {
        $css = "@media (max-width: {$maxWidth}px) {\n";
        $css .= "  .site-grid {\n";

        if (!empty($bp['col_tracks'])) {
            $css .= "    grid-template-columns: {$bp['col_tracks']};\n";
        }
        if (!empty($bp['row_tracks'])) {
            $css .= "    grid-template-rows: {$bp['row_tracks']};\n";
        }
        if (!empty($bp['areas'])) {
            $css .= "    grid-template-areas:\n      {$bp['areas']};\n";
        }
        if (isset($bp['gap_x'])) {
            $gapY = $bp['gap_y'] ?? $grid->gap_y;
            $css .= "    gap: {$gapY} {$bp['gap_x']};\n";
        }
        if (!empty($bp['container_padding'])) {
            $css .= "    padding: {$bp['container_padding']};\n";
        }

        $css .= "  }\n";

        // Mobile order
        foreach ($grid->positions as $pos) {
            if ($pos->mobile_order > 0) {
                $css .= "  .pos-{$pos->area_name} { order: {$pos->mobile_order}; }\n";
            }
        }

        // Full-bleed positions need reset on mobile
        foreach ($grid->positions as $pos) {
            if ($pos->full_bleed && $maxWidth <= 768) {
                $css .= "  .pos-{$pos->area_name} { width: 100%; margin-left: 0; }\n";
            }
        }

        $css .= "}\n";

        return $css;
    }
}
