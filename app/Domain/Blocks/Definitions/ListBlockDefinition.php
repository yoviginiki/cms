<?php

namespace App\Domain\Blocks\Definitions;

class ListBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'list'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'items'    => ['sometimes', 'array'],
            'items.*'  => ['sometimes', 'string', 'max:1000'],
            'listType' => ['sometimes', 'in:bullet,numbered,checklist'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
