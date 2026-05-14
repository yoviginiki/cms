<?php

namespace App\Domain\Blocks\Definitions;

class SidenoteBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'sidenote'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'content' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'side'    => ['sometimes', 'in:left,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
