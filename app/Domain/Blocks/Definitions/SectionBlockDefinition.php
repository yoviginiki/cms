<?php

namespace App\Domain\Blocks\Definitions;

class SectionBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'section'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        $cssDim = 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/';

        return [
            'background_color' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[#a-zA-Z0-9(),.\s]*$/'],
            'background_image' => ['sometimes', 'nullable', 'string', 'max:2048', 'url'],
            'padding' => ['sometimes', 'nullable', 'in:none,sm,md,lg,xl'], // legacy preset
            'padding_top' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'padding_bottom' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'max_width' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'anchor_id' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]*$/'],
            // BackgroundEditor fields
            'bg_type' => ['sometimes', 'nullable', 'in:none,color,gradient,image'],
            'bg_color' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[#a-zA-Z0-9(),.\s]*$/'],
            'bg_image' => ['sometimes', 'nullable', 'string', 'max:2048', 'url'],
            'bg_image_size' => ['sometimes', 'nullable', 'in:cover,contain,auto'],
            'bg_image_position' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[a-z\s%\d]*$/'],
            'bg_image_repeat' => ['sometimes', 'nullable', 'in:no-repeat,repeat,repeat-x,repeat-y'],
            'bg_scroll_effect' => ['sometimes', 'nullable', 'in:none,fixed,parallax,zoom'],
            'bg_parallax_speed' => ['sometimes', 'nullable', 'numeric', 'between:0,2'],
            'bg_gradient_type' => ['sometimes', 'nullable', 'in:linear,radial'],
            'bg_gradient_angle' => ['sometimes', 'nullable', 'integer', 'between:0,360'],
            'bg_gradient_stops' => ['sometimes', 'nullable', 'array', 'max:10'],
            'bg_gradient_stops.*.color' => ['sometimes', 'string', 'max:30', 'regex:/^[#a-zA-Z0-9(),.\s]*$/'],
            'bg_gradient_stops.*.position' => ['sometimes', 'integer', 'between:0,100'],
            'bg_overlay_color' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[#a-zA-Z0-9(),.\s]*$/'],
            'bg_overlay_opacity' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'bg_asset_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Experience Mode per-section settings
            // Experience Mode — scene presets (v1)
            'scene' => ['sometimes', 'in:fade-through,pinned-statement,scroll-gallery,reveal,parallax-split'],
            // Legacy fields (kept for backward compat, ignored by v2 runtime)
            'experienceTransition' => ['sometimes', 'in:fade,slide-up,slide-left,cover,mask-wipe,zoom'],
            'experienceEnter' => ['sometimes', 'in:none,fade-up,stagger,clip'],
            'experiencePin' => ['sometimes', 'boolean'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
