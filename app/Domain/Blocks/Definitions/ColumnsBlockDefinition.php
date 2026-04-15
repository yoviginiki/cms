<?php

namespace App\Domain\Blocks\Definitions;

class ColumnsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'columns'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'column_count' => ['required', 'integer', 'min:2', 'max:6'],
            'gap' => ['sometimes', 'in:none,small,medium,large'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 6; }
}
