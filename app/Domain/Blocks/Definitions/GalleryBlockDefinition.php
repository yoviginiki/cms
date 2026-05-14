<?php

namespace App\Domain\Blocks\Definitions;

class GalleryBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'gallery'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'images'          => ['sometimes', 'array'],
            'images.*'        => ['sometimes', 'nullable', 'string', 'max:2048'],
            'layout'          => ['sometimes', 'in:grid,masonry,carousel'],
            'columns'         => ['sometimes', 'integer', 'min:1', 'max:6'],
            'gap'             => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
