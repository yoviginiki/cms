<?php

namespace App\Domain\Blocks\Definitions;

class TocBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'toc'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'maxDepth' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'style'    => ['sometimes', 'nullable', 'string', 'max:20'],
            'sticky'   => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
