<?php

namespace App\Domain\Blocks\Definitions;

class GridBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'grid'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'templateColumns' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s(),.%\/\-]+$/'],
            'templateRows'    => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s(),.%\/\-]+$/'],
            'gap'             => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'autoFlow'        => ['sometimes', 'in:row,column,dense,row dense,column dense'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
