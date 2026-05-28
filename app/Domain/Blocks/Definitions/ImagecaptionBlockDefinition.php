<?php

namespace App\Domain\Blocks\Definitions;

class ImagecaptionBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'imagecaption'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'src'             => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'alt'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'captionPosition' => ['sometimes', 'in:below,above,overlay'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
