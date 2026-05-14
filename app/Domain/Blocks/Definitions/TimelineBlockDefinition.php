<?php

namespace App\Domain\Blocks\Definitions;

class TimelineBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'timeline'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'               => ['sometimes', 'array'],
            'items.*.date'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'items.*.title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'layout'              => ['sometimes', 'in:left,right,alternating'],
            'lineStyle'           => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
