<?php

namespace App\Domain\Blocks\Definitions;

class HeroBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'hero'; }
    public function category(): string { return 'marketing'; }

    public function validationRules(): array
    {
        return [
            // Content fields
            'title'              => ['required', 'string', 'max:255'],
            'subtitle'           => ['sometimes', 'nullable', 'string', 'max:500'],

            // Background fields (BackgroundEditor output)
            'bg_type'            => ['sometimes', 'in:none,color,gradient,image'],
            'bg_color'           => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
            'bg_gradient_type'   => ['sometimes', 'in:linear,radial'],
            'bg_gradient_angle'  => ['sometimes', 'integer', 'min:0', 'max:360'],
            'bg_gradient_stops'  => ['sometimes', 'array'],
            'bg_gradient_stops.*.color'    => ['required_with:bg_gradient_stops', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_gradient_stops.*.position' => ['required_with:bg_gradient_stops', 'integer', 'min:0', 'max:100'],
            'bg_image'           => ['sometimes', 'nullable', 'string', 'max:2048'],
            'bg_image_size'      => ['sometimes', 'in:cover,contain,auto,custom'],
            'bg_image_position'  => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(center|top|bottom|left|right)(\s+(center|top|bottom|left|right))?$/'],
            'bg_image_repeat'    => ['sometimes', 'in:no-repeat,repeat,repeat-x,repeat-y'],
            'bg_overlay_color'   => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_overlay_opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'bg_scroll_effect'   => ['sometimes', 'in:none,fixed,parallax,zoom'],
            'bg_parallax_speed'  => ['sometimes', 'numeric', 'min:0.1', 'max:1'],

            // CTA / Link fields
            'ctaText'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'ctaUrl'             => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|mailto:|tel:|\/|\.\/|\.\.\/#|\?|[a-zA-Z0-9])/i', 'not_regex:/^(javascript|data|vbscript):/i'],

            // Accessibility fields
            'alt'                => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
