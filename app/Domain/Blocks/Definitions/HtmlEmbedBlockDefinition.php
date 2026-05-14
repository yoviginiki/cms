<?php

namespace App\Domain\Blocks\Definitions;

class HtmlEmbedBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'html-embed'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'html' => ['sometimes', 'string', 'max:65536'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
