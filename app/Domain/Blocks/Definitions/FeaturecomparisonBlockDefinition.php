<?php

namespace App\Domain\Blocks\Definitions;

class FeaturecomparisonBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'featurecomparison'; }
    public function category(): string { return 'commerce'; }

    public function validationRules(): array
    {
        return [
            'plans'            => ['sometimes', 'array'],
            'plans.*.name'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'plans.*.price'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'features'         => ['sometimes', 'array'],
            'features.*.name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'features.*.values'  => ['sometimes', 'array'],
            'features.*.values.*'=> ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
