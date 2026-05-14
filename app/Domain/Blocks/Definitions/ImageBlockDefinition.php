<?php

namespace App\Domain\Blocks\Definitions;

class ImageBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'image'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'asset_id' => ['sometimes', 'nullable', 'uuid'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'size' => ['sometimes', 'in:small,medium,large,full'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
