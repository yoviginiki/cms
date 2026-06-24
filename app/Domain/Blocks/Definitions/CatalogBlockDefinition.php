<?php

namespace App\Domain\Blocks\Definitions;

class CatalogBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'catalog'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'items' => ['sometimes', 'array', 'max:20'],
            'items.*.title' => ['sometimes', 'string', 'max:255'],
            'items.*.subtitle' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.content' => ['sometimes', 'string'],
            'items.*.contentSecondary' => ['sometimes', 'nullable', 'string'],
            'items.*.images' => ['sometimes', 'array', 'max:10'],
            'items.*.images.*' => ['sometimes', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'headerLabels' => ['sometimes', 'array', 'max:4'],
            'headerLabels.*' => ['sometimes', 'nullable', 'string', 'max:50'],
            'openFirst' => ['sometimes', 'boolean'],
            'imageHeight' => ['sometimes', 'nullable', 'string', 'max:20'],
            'imageFilter' => ['sometimes', 'in:none,grayscale,sepia'],
            'imageHoverReveal' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,a[href|target],ul,ol,li,span[style]',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
