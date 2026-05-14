<?php

namespace App\Domain\Blocks\Definitions;

class ImagecaptionBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'imagecaption'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'src'             => ['sometimes', 'nullable', 'string', 'max:2048'],
            'alt'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'captionPosition' => ['sometimes', 'in:below,above,overlay'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
