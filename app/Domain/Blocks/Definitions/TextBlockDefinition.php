<?php

namespace App\Domain\Blocks\Definitions;

class TextBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'text'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'content' => ['sometimes', 'string'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,u,a[href|target],ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,code,pre',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
