<?php

namespace App\Domain\Blocks\Definitions;

class TableBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'table'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'headers'    => ['sometimes', 'array'],
            'headers.*'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows'       => ['sometimes', 'array'],
            'rows.*'     => ['sometimes', 'array'],
            'rows.*.*'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'striped'    => ['sometimes', 'boolean'],
            'compact'    => ['sometimes', 'boolean'],
            'caption'    => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
