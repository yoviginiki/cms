<?php

namespace App\Domain\Blocks\Definitions;

class FeaturegridBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'featuregrid'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'               => ['sometimes', 'array'],
            'items.*.icon'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'items.*.title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'columns'             => ['sometimes', 'integer', 'min:1', 'max:6'],
            'style'               => ['sometimes', 'in:icon-top,icon-left'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
