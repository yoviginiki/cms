<?php

namespace App\Domain\Blocks\Definitions;

class ParagraphBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'paragraph'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'content' => ['sometimes', 'string'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,u,s,a[href|target|rel],span[style],sub,sup',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
