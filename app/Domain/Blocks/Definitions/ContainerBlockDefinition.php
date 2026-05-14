<?php

namespace App\Domain\Blocks\Definitions;

class ContainerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'container'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'maxWidth'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%|vw)?$/'],
            'centered'  => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
