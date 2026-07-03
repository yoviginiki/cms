<?php

namespace App\Domain\Blocks\Definitions;

class VideoBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'video'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'url' => ['sometimes', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'autoplay' => ['sometimes', 'boolean'],
            'muted' => ['sometimes', 'boolean'],
            'loop' => ['sometimes', 'boolean'],
            'controls' => ['sometimes', 'boolean'],
            'playsinline' => ['sometimes', 'boolean'],
            'preload' => ['sometimes', 'in:none,metadata,auto'],
            'poster' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'heroMode' => ['sometimes', 'boolean'],
            'shape' => ['sometimes', 'in:none,capsule,circle,rounded,custom'],
            'shapeRadius' => ['sometimes', 'nullable', 'string', 'max:50'],
            'minHeight' => ['sometimes', 'nullable', 'string', 'max:20'],
            'overlay' => ['sometimes', 'boolean'],
            'overlayColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'overlayOpacity' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'preTitle' => ['sometimes', 'nullable', 'string', 'max:100'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'textColor' => ['sometimes', 'nullable', 'string', 'max:50'],
        ] + \App\Support\Blocks\SliderAnimation::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
