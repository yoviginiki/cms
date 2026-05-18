<?php

namespace App\Domain\Blocks\Definitions;

class PostMetaBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-meta'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'showDate' => ['sometimes', 'boolean'],
            'showAuthor' => ['sometimes', 'boolean'],
            'showCategory' => ['sometimes', 'boolean'],
            'separator' => ['sometimes', 'string', 'max:5'],
            'textAlign' => ['sometimes', 'nullable', 'in:,left,center,right']
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
