<?php

namespace App\Domain\Blocks\Definitions;

class StatsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'stats'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'           => ['sometimes', 'array'],
            'items.*.value'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.label'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.prefix'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'items.*.suffix'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'columns'         => ['sometimes', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
