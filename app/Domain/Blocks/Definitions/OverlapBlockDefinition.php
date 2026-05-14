<?php

namespace App\Domain\Blocks\Definitions;

class OverlapBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'overlap'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'offsetY' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)$/'],
            'offsetX' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^-?\d+(\.\d+)?(px|rem|em|%)$/'],
            'zIndex'  => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 10; }
}
