<?php

namespace App\Domain\Blocks\Definitions;

class FullbleedBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'fullbleed'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'src'             => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'alt'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'overlayText'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'overlayPosition' => ['sometimes', 'in:center,top-left,top-right,bottom-left,bottom-right'],
            'scrimOpacity'    => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'minHeight'       => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|vh|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
