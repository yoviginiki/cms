<?php

namespace App\Domain\Blocks\Definitions;

class DropcapBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'dropcap'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'content'  => ['sometimes', 'string'],
            'capSize'  => ['sometimes', 'integer', 'min:1', 'max:10'],
            'capColor' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\)|oklch\([\d\s,.\/%]+\))$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
