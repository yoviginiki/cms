<?php

namespace App\Domain\Blocks\Definitions;

class GalleryBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'gallery'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return GalleryImageRules::rules('images') + [
            'layout'          => ['sometimes', 'in:grid,masonry,carousel'],
            'columns'         => ['sometimes', 'integer', 'min:1', 'max:8'],
            'gap'             => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'aspect'          => ['sometimes', 'nullable', 'in:square,4:3,3:2,16:9,natural'],
            'lightbox'        => ['sometimes', 'boolean'],
            'captions'        => ['sometimes', 'boolean'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
