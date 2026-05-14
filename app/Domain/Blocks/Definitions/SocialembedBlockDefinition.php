<?php

namespace App\Domain\Blocks\Definitions;

class SocialembedBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'socialembed'; }
    public function category(): string { return 'embed'; }

    public function validationRules(): array
    {
        return [
            'url'      => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'platform' => ['sometimes', 'in:auto,twitter,instagram,youtube,tiktok'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
