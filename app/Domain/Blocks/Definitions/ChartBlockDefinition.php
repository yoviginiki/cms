<?php

namespace App\Domain\Blocks\Definitions;

class ChartBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'chart'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'chartType'    => ['sometimes', 'in:bar,line,pie,donut'],
            'data'         => ['sometimes', 'array'],
            'data.*.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data.*.value' => ['sometimes', 'numeric'],
            'title'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'showLegend'   => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
